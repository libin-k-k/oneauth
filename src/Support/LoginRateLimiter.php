<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\RateLimiter;
use Libinkk\OneAuth\Exceptions\AuthenticationException;

class LoginRateLimiter
{
    public function hitOrFail(string $identifier, string $ip): void
    {
        $key = 'oneauth-login:' . sha1($identifier . '|' . $ip);
        $maxAttempts = (int) config('oneauth.security.max_login_attempts', 5);
        $lockoutSeconds = (int) config('oneauth.security.lockout_seconds', 300);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new AuthenticationException('Too many login attempts. Try again later.');
        }

        RateLimiter::hit($key, $lockoutSeconds);
    }
}
