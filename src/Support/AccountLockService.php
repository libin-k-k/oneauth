<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\AccountLocked;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\AccountLock;

class AccountLockService
{
    public function ensureNotLocked(string $identifier): void
    {
        $lock = AccountLock::query()->where('identifier', $identifier)->first();
        if (!$lock) {
            return;
        }

        if ($lock->locked_until && $lock->locked_until->isFuture()) {
            throw new AuthenticationException('Account is locked. Try again later.');
        }

        if ($lock->locked_until && $lock->locked_until->isPast()) {
            $lock->delete();
        }
    }

    public function lock(string $identifier, ?int $seconds = null, string $reason = 'manual', mixed $user = null): void
    {
        $seconds ??= (int) config('oneauth.security.lockout_seconds', 300);

        AccountLock::query()->updateOrCreate(
            ['identifier' => $identifier],
            [
                'locked_until' => now()->addSeconds(max(1, $seconds)),
                'reason' => $reason,
                'meta' => ['locked_at' => now()->toIso8601String()],
            ]
        );

        if ($user) {
            Event::dispatch(new AccountLocked($user, ['identifier' => $identifier, 'reason' => $reason]));
        }
    }

    public function unlock(string $identifier): bool
    {
        return AccountLock::query()->where('identifier', $identifier)->delete() > 0;
    }

    public function isLocked(string $identifier): bool
    {
        $lock = AccountLock::query()->where('identifier', $identifier)->first();

        return $lock !== null && $lock->locked_until && $lock->locked_until->isFuture();
    }
}
