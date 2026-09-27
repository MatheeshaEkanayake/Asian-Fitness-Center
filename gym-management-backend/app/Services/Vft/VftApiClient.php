<?php

namespace App\Services\Vft;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP client for the VFT GYM API (config/vft.php).
 *
 * - Signs in with VFT_EMAIL/VFT_PASSWORD and caches the token until shortly
 *   before it expires (tokens last ~1 hour; the JWT `exp` is in milliseconds).
 * - On a 401 it signs in again once and retries the request.
 * - Retries connection failures only; any other failure throws
 *   VftApiException.
 * - Logs every request and raw response to the `vft` log channel. The
 *   password and token are never logged.
 *
 * The live server's /api/user/auth endpoint rejects valid tokens, so token
 * validity is judged from its expiry and 401s instead.
 */
class VftApiClient
{
    public const TOKEN_CACHE_KEY = 'vft.api_token';

    // ---------------------------------------------------------------------
    // Resources
    // ---------------------------------------------------------------------

    public function listAreas(): mixed
    {
        return $this->request('GET', '/api/area');
    }

    /** @param array{AreaID?: string, AreaName: string, Description?: string} $area */
    public function createArea(array $area): mixed
    {
        return $this->request('POST', '/api/area', $area);
    }

    public function updateArea(int|string $id, array $area): mixed
    {
        return $this->request('PUT', "/api/area/{$id}", $area);
    }

    public function listDevices(): mixed
    {
        return $this->request('GET', '/api/device');
    }

    /** @param array{DevSN: string, DevName: string, TimeZone: string, AreaID: int, IsReboot?: int} $device */
    public function createDevice(array $device): mixed
    {
        return $this->request('POST', '/api/device', $device);
    }

    public function updateDevice(int|string $id, array $device): mixed
    {
        return $this->request('PUT', "/api/device/{$id}", $device);
    }

    /** VFT calls these "employees" (the /api/template endpoints). */
    public function listEmployees(): mixed
    {
        return $this->request('GET', '/api/template');
    }

    /** @param array{EmpId: string, EmpName: string} $employee */
    public function createEmployee(array $employee): mixed
    {
        return $this->request('POST', '/api/template/add', $employee);
    }

    public function updateEmployee(int|string $id, array $employee): mixed
    {
        return $this->request('PUT', "/api/template/edit/{$id}", $employee);
    }

    /**
     * Send a raw ZKTeco command. Use VftDeviceCommandService instead of
     * calling this directly — it builds and escapes the command strings.
     *
     * It's not yet known whether VFT returns results here or only queues
     * the command for the device; the raw response is returned and logged.
     */
    public function sendDeviceCommand(string $devSn, string $content, string $type = 'General'): mixed
    {
        return $this->request('POST', '/api/devicecmd', [
            'DevSN' => $devSn,
            'Type' => $type,
            'Content' => $content,
        ]);
    }

    // ---------------------------------------------------------------------
    // Auth
    // ---------------------------------------------------------------------

    /** Sign in and cache a fresh token. */
    public function signIn(): string
    {
        $email = config('vft.email');
        $password = config('vft.password');

        if (! $email || ! $password) {
            throw new VftApiException('VFT_EMAIL / VFT_PASSWORD are not set.');
        }

        $this->log()->info('→ POST /api/user/ (sign in)', ['email' => $email]);

        $response = $this->send('POST', '/api/user/', ['email' => $email, 'password' => $password], withToken: false);
        $token = $response->json('token');

        $this->logResponse('/api/user/', $response, redact: ['token']);

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new VftApiException(
                'VFT sign-in failed.',
                $response->status(),
                Str::limit($response->body(), 500),
            );
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, $this->tokenTtl($token));

        return $token;
    }

    public function forgetToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    private function token(): string
    {
        return Cache::get(self::TOKEN_CACHE_KEY) ?? $this->signIn();
    }

    /** Seconds to cache the token: its own expiry minus 5 minutes. */
    private function tokenTtl(string $token): int
    {
        $fallback = config('vft.token_ttl_seconds');
        $parts = explode('.', $token);
        $claims = count($parts) === 3
            ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true)
            : null;
        $exp = is_array($claims) ? ($claims['exp'] ?? null) : null;

        if (! is_numeric($exp)) {
            return $fallback;
        }

        // VFT writes `exp` in milliseconds (standard JWTs use seconds).
        $exp = $exp > 100_000_000_000 ? (int) ($exp / 1000) : (int) $exp;
        $ttl = $exp - time() - 300;

        return $ttl > 60 ? $ttl : $fallback;
    }

    // ---------------------------------------------------------------------
    // Transport
    // ---------------------------------------------------------------------

    /**
     * Authenticated request. Returns the decoded JSON, or the raw body when
     * the response isn't JSON (e.g. plain-text replies).
     */
    public function request(string $method, string $path, array $data = []): mixed
    {
        $this->log()->info("→ {$method} {$path}", $data ? ['body' => $data] : []);

        $response = $this->send($method, $path, $data);

        if ($response->status() === 401) {
            $this->log()->info("401 on {$path} — signing in again and retrying once.");
            $this->forgetToken();
            $response = $this->send($method, $path, $data);
        }

        $this->logResponse($path, $response);

        if (! $response->successful()) {
            throw new VftApiException(
                "VFT {$method} {$path} failed with HTTP {$response->status()}.",
                $response->status(),
                Str::limit($response->body(), 500),
            );
        }

        $json = $response->json();

        return $json ?? $response->body();
    }

    private function send(string $method, string $path, array $data, bool $withToken = true): Response
    {
        $request = Http::baseUrl(config('vft.base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout(config('vft.timeout'))
            ->retry(
                max(1, config('vft.retries') + 1),
                config('vft.retry_sleep_ms'),
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false,
            );

        if ($withToken) {
            $request = $request->withHeaders([config('vft.token_header') => $this->token()]);
        }

        try {
            return $request->send($method, $path, $data ? ['json' => $data] : []);
        } catch (ConnectionException $e) {
            $this->log()->error("✗ {$method} {$path} — connection failed", ['error' => $e->getMessage()]);

            throw new VftApiException("Could not reach VFT ({$e->getMessage()}).", previous: $e);
        }
    }

    private function logResponse(string $path, Response $response, array $redact = []): void
    {
        $body = $response->json();

        if (is_array($body)) {
            foreach ($redact as $key) {
                if (array_key_exists($key, $body)) {
                    $body[$key] = '[redacted]';
                }
            }
            $body = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $body = $response->body();
        }

        $this->log()->info("← {$response->status()} {$path}", ['body' => Str::limit($body, 4000)]);
    }

    private function log(): \Psr\Log\LoggerInterface
    {
        return Log::channel('vft');
    }
}
