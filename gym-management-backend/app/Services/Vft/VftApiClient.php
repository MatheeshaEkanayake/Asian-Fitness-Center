<?php

namespace App\Services\Vft;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP client for the VFT GYM API — endpoints as in the VFT_GYM_API_V2.0
 * Postman collection (docs/). One method per endpoint; VftDeviceCommandService
 * decides what to call and respects VFT_MODE.
 *
 * - Signs in with VFT_EMAIL/VFT_PASSWORD and caches the token until shortly
 *   before it expires (tokens last ~1 hour; the JWT `exp` is in milliseconds).
 * - On a 401 it signs in again once and retries the request.
 * - VFT answers some failures with HTTP 200 and a message such as "Cannot
 *   delete Employee with id=166. The Employee was not found!" — those are
 *   treated as failures too (see failureMessage()).
 * - Retries connection failures only; any other failure throws
 *   VftApiException.
 * - Logs every request and raw response to the `vft` log channel. The
 *   password and token are never logged.
 *
 * VFT calls the people on the device "employees" (the /api/template
 * endpoints); their EmpId is our Member ID number (the door PIN).
 */
class VftApiClient
{
    public const TOKEN_CACHE_KEY = 'vft.api_token';

    // ---------------------------------------------------------------------
    // Areas and devices
    // ---------------------------------------------------------------------

    public function listAreas(): mixed
    {
        return $this->request('GET', '/api/area');
    }

    /** @param array{AreaName: string, Description?: string} $area */
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

    /** @param array{DevName?: string, AreaID?: int} $device */
    public function updateDevice(string $devSn, array $device): mixed
    {
        return $this->request('PUT', '/api/device/'.rawurlencode($devSn), $device);
    }

    // ---------------------------------------------------------------------
    // Employees (people on the device)
    // ---------------------------------------------------------------------

    public function listEmployees(): mixed
    {
        return $this->request('GET', '/api/template');
    }

    /** @param array{EmpId: string, EmpName: string, AreaID: int} $employee */
    public function createEmployee(array $employee): mixed
    {
        return $this->request('POST', '/api/template/add', $employee);
    }

    /** @param array{EmpName: string} $employee */
    public function updateEmployee(string $empId, array $employee): mixed
    {
        return $this->request('PUT', '/api/template/edit/'.rawurlencode($empId), $employee);
    }

    /** Remove from the VFT cloud. */
    public function deleteEmployee(string $empId): mixed
    {
        return $this->request('DELETE', '/api/template/'.rawurlencode($empId));
    }

    /** Remove from every device (user and fingerprints/face). */
    public function deleteEmployeeFromDevices(string $empId): mixed
    {
        return $this->request('DELETE', '/api/template/fromdev/'.rawurlencode($empId));
    }

    /**
     * Copy employees (with their fingerprints/face) from the cloud to one
     * device — the collection's "TransferUserToDevice".
     *
     * @param  string[]  $empIds
     */
    public function syncEmployeesToDevice(string $devSn, array $empIds): mixed
    {
        return $this->request('POST', '/api/template/syncsometoone', query: [
            'DevSN' => $devSn,
            'empidlist' => implode(',', $empIds),
        ]);
    }

    // ---------------------------------------------------------------------
    // Door access (queued for the device)
    // ---------------------------------------------------------------------

    /** Dates as YYYYMMDD; "0" = no limit. */
    public function setValidPeriod(string $devSn, string $empId, string $startDate, string $endDate): mixed
    {
        return $this->request('POST', '/api/devicecmd/validperiod', query: [
            'DevSN' => $devSn,
            'EmployeeId' => $empId,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
        ]);
    }

    public function grantAccess(string $devSn, string $empId): mixed
    {
        return $this->request('POST', '/api/devicecmd/accessgrant', query: ['DevSN' => $devSn, 'EmployeeId' => $empId]);
    }

    public function blockAccess(string $devSn, string $empId): mixed
    {
        return $this->request('POST', '/api/devicecmd/accessblock', query: ['DevSN' => $devSn, 'EmployeeId' => $empId]);
    }

    public function openDoor(string $devSn): mixed
    {
        return $this->request('POST', '/api/devicecmd/dooropen', query: ['DevSN' => $devSn]);
    }

    public function rebootDevice(string $devSn): mixed
    {
        return $this->request('POST', '/api/devicecmd/reboot', query: ['DevSN' => $devSn]);
    }

    // ---------------------------------------------------------------------
    // Enrollment — puts the device into scan mode for that person
    // ---------------------------------------------------------------------

    /** @param int $fingerId 0–9 */
    public function enrollFinger(string $devSn, string $empId, int $fingerId): mixed
    {
        return $this->request('POST', '/api/devicecmd/enrollfinger', query: [
            'DevSN' => $devSn,
            'employeeId' => $empId,
            'fingerId' => $fingerId,
        ]);
    }

    public function enrollFace(string $devSn, string $empId): mixed
    {
        return $this->request('POST', '/api/devicecmd/enrollface', query: ['DevSN' => $devSn, 'employeeId' => $empId]);
    }

    // ---------------------------------------------------------------------
    // Queued commands
    // ---------------------------------------------------------------------

    public function listCommands(int $limit = 20, int $page = 1): mixed
    {
        return $this->request('GET', '/api/devicecmd/limit', query: ['limit' => $limit, 'page' => $page]);
    }

    public function listDeviceCommands(string $devSn): mixed
    {
        return $this->request('GET', '/api/devicecmd/'.rawurlencode($devSn));
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
    public function request(string $method, string $path, array $data = [], array $query = []): mixed
    {
        $path = $query ? $path.'?'.http_build_query($query) : $path;
        $this->log()->info("→ {$method} {$path}", $data ? ['body' => $data] : []);

        $response = $this->send($method, $path, $data);

        if ($response->status() === 401) {
            $this->log()->info("401 on {$path} — signing in again and retrying once.");
            $this->forgetToken();
            $response = $this->send($method, $path, $data);
        }

        $this->logResponse($path, $response);

        $json = $response->json();

        if (! $response->successful()) {
            $reason = is_array($json) && is_string($json['message'] ?? null) ? ": {$json['message']}" : '';

            throw new VftApiException(
                "VFT {$method} {$path} failed with HTTP {$response->status()}{$reason}.",
                $response->status(),
                Str::limit($response->body(), 500),
            );
        }

        if ($failure = $this->failureMessage($json)) {
            throw new VftApiException(
                "VFT {$method} {$path} failed: {$failure}",
                $response->status(),
                Str::limit($response->body(), 500),
            );
        }

        return $json ?? $response->body();
    }

    /**
     * VFT reports some failures with HTTP 200, e.g.
     * {"message": "Cannot delete Employee with id=166. The Employee was not found!"}.
     */
    private function failureMessage(mixed $json): ?string
    {
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? null) : null;

        if (! is_string($message)) {
            return null;
        }

        return preg_match('/\b(cannot|can\'t|not found|error|fail(ed|ure)?|invalid|unauthori[sz]ed|denied)\b/i', $message)
            ? $message
            : null;
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
