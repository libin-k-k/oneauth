<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\RateLimiter;
use Libinkk\OneAuth\Exceptions\AuthenticationException;

class LoginRateLimiter
{
    public function __construct(private AccountLockService $accountLocks)
    {
    }

    public function ensureNotLocked(string $identifier, string $ip): void
    {
        $key = $this->key($identifier, $ip);
        $maxAttempts = (int) config('oneauth.security.max_login_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->accountLocks->lock(
                $identifier,
                (int) config('oneauth.security.lockout_seconds', 300),
                'too_many_attempts'
            );
            throw new AuthenticationException('Too many login attempts. Try again later.');
        }

        $this->accountLocks->ensureNotLocked($identifier);
    }

    public function hit(string $identifier, string $ip): void
    {
        $lockoutSeconds = (int) config('oneauth.security.lockout_seconds', 300);
        $key = $this->key($identifier, $ip);
        RateLimiter::hit($key, $lockoutSeconds);

        $maxAttempts = (int) config('oneauth.security.max_login_attempts', 5);
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->accountLocks->lock($identifier, $lockoutSeconds, 'too_many_attempts');
        }
    }

    public function clear(string $identifier, string $ip): void
    {
        RateLimiter::clear($this->key($identifier, $ip));
        $this->accountLocks->unlock($identifier);
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
