<?php

namespace Tests\Feature\Vft\Concerns;

use App\Services\Vft\VftApiClient;
use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A fake VFT server: device TESTSN001 in area 3, `$vftEmployees` as the
 * cloud's employee list, and a generic success reply for everything else.
 * `$vftFailure` can return a response for a call to make it fail.
 */
trait FakesVft
{
    /** @var list<array<string, mixed>> */
    protected array $vftEmployees = [];

    /** fn (string $call): ?PromiseInterface */
    protected ?Closure $vftFailure = null;

    protected function fakeVft(): void
    {
        Cache::put(VftApiClient::TOKEN_CACHE_KEY, 'test-token', 600);

        Http::fake(function (Request $request) {
            $call = $this->describeVftCall($request);

            if ($this->vftFailure && ($response = ($this->vftFailure)($call))) {
                return $response;
            }

            return match ($call) {
                'GET /api/template' => Http::response($this->vftEmployees),
                'GET /api/device' => Http::response([['DevSN' => 'TESTSN001', 'Area' => ['AreaID' => 3, 'AreaName' => 'Gym']]]),
                default => Http::response(['message' => 'Command added successfully']),
            };
        });
    }

    /** e.g. "POST /api/devicecmd/accessgrant?DevSN=TESTSN001&EmployeeId=42" or "POST /api/template/add {...}" */
    protected function describeVftCall(Request $request): string
    {
        $url = urldecode(substr($request->url(), strlen('https://vft.test')));
        $body = $request->body() !== '' ? ' '.$request->body() : '';

        return $request->method().' '.$url.$body;
    }

    /** @return list<string> every call except the reads (GET) */
    protected function vftWrites(): array
    {
        return Http::recorded()
            ->map(fn ($pair) => $this->describeVftCall($pair[0]))
            ->reject(fn (string $call) => str_starts_with($call, 'GET '))
            ->values()
            ->all();
    }
}
