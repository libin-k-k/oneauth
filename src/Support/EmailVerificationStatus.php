<?php

namespace Libinkk\OneAuth\Support;

class EmailVerificationStatus
{
    public static function tracksVerification(mixed $user): bool
    {
        return is_object($user)
            && method_exists($user, 'getAttributes')
            && array_key_exists('email_verified_at', $user->getAttributes());
    }

    public static function isVerified(mixed $user): bool
    {
        if (!self::tracksVerification($user)) {
            return true;
        }

        return !is_null($user->getAttribute('email_verified_at'));
    }

    public static function markVerified(mixed $user): void
    {
        if (!is_object($user) || !method_exists($user, 'forceFill')) {
            return;
        }

        if (!self::tracksVerification($user) && !(method_exists($user, 'isFillable') && $user->isFillable('email_verified_at'))) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
    }
}
