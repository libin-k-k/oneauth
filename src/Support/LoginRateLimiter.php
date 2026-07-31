<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\RateLimiter;
use Libinkk\OneAuth\Exceptions\AuthenticationException;

class LoginRateLimiter
{
    public function ensureNotLocked(string $identifier, string $ip): void
    {
        $key = $this->key($identifier, $ip);
        $maxAttempts = (int) config('oneauth.security.max_login_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new AuthenticationException('Too many login attempts. Try again later.');
        }
    }

    public function hit(string $identifier, string $ip): void
    {
        $lockoutSeconds = (int) config('oneauth.security.lockout_seconds', 300);
        RateLimiter::hit($this->key($identifier, $ip), $lockoutSeconds);
    }

    public function clear(string $identifier, string $ip): void
    {
        RateLimiter::clear($this->key($identifier, $ip));
    }

    /**
     * @deprecated Prefer ensureNotLocked / hit / clear.
     */
    public function hitOrFail(string $identifier, string $ip): void
    {
        $this->ensureNotLocked($identifier, $ip);
        $this->hit($identifier, $ip);
    }

    protected function key(string $identifier, string $ip): string
    {
        return 'oneauth-login:' . sha1($identifier . '|' . $ip);
    }
}
