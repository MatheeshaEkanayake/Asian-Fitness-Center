<?php

namespace Tests\Feature\Vft;

use App\Services\Vft\VftApiClient;
use App\Services\Vft\VftApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VftApiClientTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = storage_path('logs/vft-test.log');
        @unlink($this->logFile);
        config(['logging.channels.vft' => ['driver' => 'single', 'path' => $this->logFile]]);
    }

    /** A JWT-shaped token whose `exp` is in milliseconds, like VFT's. */
    private function token(string $id, int $expiresInSeconds = 3600): string
    {
        $claims = ['sub' => 1, 'iat' => time(), 'exp' => (time() + $expiresInSeconds) * 1000];

        return 'eyJhbGciOiJIUzI1NiJ9.'.rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=').".sig-{$id}";
    }

    public function test_signs_in_and_sends_token_in_api_token_header(): void
    {
        $token = $this->token('a');
        Http::fake([
            'vft.test/api/user/' => Http::response(['success' => 'logged in successfully', 'token' => $token]),
            'vft.test/api/device' => Http::response([['DevSN' => 'TESTSN001']]),
        ]);

        $devices = app(VftApiClient::class)->listDevices();

        $this->assertSame('TESTSN001', $devices[0]['DevSN']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://vft.test/api/user/'
            && $r['email'] === 'gym@example.test'
            && $r['password'] === 's3cret-test-pw');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://vft.test/api/device'
            && $r->hasHeader('ApiToken', $token));
    }

    public function test_reuses_cached_token_instead_of_signing_in_again(): void
    {
        Http::fake([
            'vft.test/api/user/' => Http::response(['token' => $this->token('a')]),
            'vft.test/api/*' => Http::response([]),
        ]);

        $client = app(VftApiClient::class);
        $client->listDevices();
        $client->listAreas();
        $client->listEmployees();

        $signIns = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/api/user/'));
        $this->assertCount(1, $signIns);
        Http::assertSentCount(4);
    }

    public function test_caches_token_until_shortly_before_its_millisecond_expiry(): void
    {
        Http::fake(['vft.test/api/user/' => Http::response(['token' => $this->token('a', 3600)])]);

        app(VftApiClient::class)->signIn();

        $this->assertNotNull(Cache::get(VftApiClient::TOKEN_CACHE_KEY));
        $this->travel(3200)->seconds();
        $this->assertNotNull(Cache::get(VftApiClient::TOKEN_CACHE_KEY), 'still valid ~53 min in');
        $this->travel(200)->seconds();
        $this->assertNull(Cache::get(VftApiClient::TOKEN_CACHE_KEY), 'dropped 5 min before expiry');
    }

    public function test_on_401_signs_in_again_once_and_retries(): void
    {
        $old = $this->token('old');
        $new = $this->token('new');
        Cache::put(VftApiClient::TOKEN_CACHE_KEY, $old, 600);

        Http::fake([
            'vft.test/api/user/' => Http::response(['token' => $new]),
            'vft.test/api/device' => Http::sequence()
                ->push('Unauthorized', 401)
                ->push([['DevSN' => 'TESTSN001']], 200),
        ]);

        $devices = app(VftApiClient::class)->listDevices();

        $this->assertSame('TESTSN001', $devices[0]['DevSN']);
        $deviceCalls = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/api/device'))->values();
        $this->assertCount(2, $deviceCalls);
        $this->assertTrue($deviceCalls[0][0]->hasHeader('ApiToken', $old));
        $this->assertTrue($deviceCalls[1][0]->hasHeader('ApiToken', $new));
        $this->assertSame($new, Cache::get(VftApiClient::TOKEN_CACHE_KEY));
    }

    public function test_gives_up_after_one_relogin_if_still_unauthorized(): void
    {
        Http::fake([
            'vft.test/api/user/' => Http::response(['token' => $this->token('a')]),
            'vft.test/api/device' => Http::response('Unauthorized', 401),
        ]);

        try {
            app(VftApiClient::class)->listDevices();
            $this->fail('Expected VftApiException');
        } catch (VftApiException $e) {
            $this->assertSame(401, $e->status);
        }

        $this->assertCount(2, Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/api/device')));
    }

    public function test_failed_sign_in_throws(): void
    {
        Http::fake(['vft.test/api/user/' => Http::response(['error' => 'invalid'], 400)]);

        $this->expectException(VftApiException::class);
        $this->expectExceptionMessage('VFT sign-in failed.');

        app(VftApiClient::class)->listDevices();
    }

    public function test_server_error_throws_with_status_and_body(): void
    {
        Http::fake([
            'vft.test/api/user/' => Http::response(['token' => $this->token('a')]),
            'vft.test/api/devicecmd' => Http::response('boom', 500),
        ]);

        try {
            app(VftApiClient::class)->sendDeviceCommand('TESTSN001', 'CONTROL DEVICE 01010105');
            $this->fail('Expected VftApiException');
        } catch (VftApiException $e) {
            $this->assertSame(500, $e->status);
            $this->assertSame('boom', $e->responseBody);
        }
    }

    public function test_connection_failures_are_retried_then_throw(): void
    {
        config(['vft.retries' => 2]);
        Cache::put(VftApiClient::TOKEN_CACHE_KEY, $this->token('a'), 600);
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('timed out');
        });

        try {
            app(VftApiClient::class)->listDevices();
            $this->fail('Expected VftApiException');
        } catch (VftApiException $e) {
            $this->assertStringContainsString('Could not reach VFT', $e->getMessage());
        }

        $this->assertSame(3, $attempts, '1 try + 2 retries');
    }

    public function test_logs_requests_and_responses_but_never_password_or_token(): void
    {
        $token = $this->token('secret-token-value');
        Http::fake([
            'vft.test/api/user/' => Http::response(['token' => $token]),
            'vft.test/api/area' => Http::response([['AreaID' => 1, 'AreaName' => 'Main']]),
        ]);

        app(VftApiClient::class)->listAreas();

        $log = file_get_contents($this->logFile);
        $this->assertStringContainsString('GET /api/area', $log);
        $this->assertStringContainsString('AreaName', $log, 'raw response is logged');
        $this->assertStringContainsString('gym@example.test', $log);
        $this->assertStringNotContainsString('s3cret-test-pw', $log);
        $this->assertStringNotContainsString('secret-token-value', $log);
    }
}
