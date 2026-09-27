<?php

namespace App\Services\Vft;

/**
 * Outcome of one device command. `sent` is false in `off`/`log` mode
 * (nothing reached VFT); `response` is VFT's raw reply when sent.
 */
final class VftCommandResult
{
    public function __construct(
        public readonly string $mode,
        public readonly string $devSn,
        public readonly string $content,
        public readonly bool $sent,
        public readonly mixed $response = null,
    ) {}
}
