<?php

namespace App\Services\Vft;

use RuntimeException;

/**
 * Any failed call to the VFT GYM API: connection errors, non-2xx
 * responses, or a sign-in that returns no token.
 */
class VftApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }
}
