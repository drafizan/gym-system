<?php

namespace App\Services;

class AccessSyncResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $message,
        public readonly ?array $response = null,
    ) {
        //
    }

    public static function success(string $message = 'Synced', ?array $response = null): self
    {
        return new self(true, $message, $response);
    }

    public static function failed(string $message, ?array $response = null): self
    {
        return new self(false, $message, $response);
    }
}
