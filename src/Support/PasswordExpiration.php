<?php

namespace Libinkk\OneAuth\Support;

use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\PasswordHistory;

class PasswordExpiration
{
    public function assertNotExpired(mixed $user): void
    {
        $days = (int) config('oneauth.security.password_expires_days', 0);
        if ($days <= 0) {
            return;
        }

        $latest = PasswordHistory::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->latest('changed_at')
            ->first();

        $changedAt = $latest?->changed_at ?? ($user->created_at ?? null);
        if (!$changedAt) {
            return;
        }

        if ($changedAt->copy()->addDays($days)->isPast()) {
            throw new AuthenticationException('Password has expired. Please reset your password.');
        }
    }
}
