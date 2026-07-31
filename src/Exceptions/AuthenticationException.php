<?php

namespace Libinkk\OneAuth\Exceptions;

class AuthenticationException extends OneAuthException
{
    public function status(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'AUTHENTICATION_FAILED';
    }
}
