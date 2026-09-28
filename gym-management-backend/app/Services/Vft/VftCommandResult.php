<?php

namespace App\Services\Vft;

/**
 * Outcome of one VFT action (e.g. `accessgrant` with its parameters).
 * `sent` is false in `off`/`log` mode (nothing reached VFT); `response` is
 * VFT's raw reply when sent.
 */
final class VftCommandResult
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public readonly string $mode,
        public readonly string $devSn,
        public readonly string $action,
        public readonly array $params,
        public readonly bool $sent,
        public readonly mixed $response = null,
    ) {}

    /** e.g. "validperiod EmployeeId=42 StartDate=20261010 EndDate=20261109" — for logs and console output. */
    public function describe(): string
    {
        $params = collect($this->params)
            ->map(fn ($value, $key) => $key.'='.(is_array($value) ? implode(',', $value) : $value))
            ->implode(' ');

        return trim("{$this->action} {$params}");
    }
}
