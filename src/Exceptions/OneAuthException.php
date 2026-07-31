<?php

namespace Libinkk\OneAuth\Exceptions;

use RuntimeException;

class OneAuthException extends RuntimeException
{
    public function status(): int
    {
        return 400;
    }

    public function errorCode(): string
    {
        return 'ONEAUTH_ERROR';
    }

    public function context(): array
    {
        return [];
    }

    public function toArray(): array
    {
        return array_merge([
            'message' => $this->getMessage(),
            'error' => $this->errorCode(),
        ], $this->context());
    }
}
