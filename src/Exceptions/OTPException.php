<?php

namespace Libinkk\OneAuth\Exceptions;

class OTPException extends OneAuthException
{
    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'OTP_FAILED';
    }
}
