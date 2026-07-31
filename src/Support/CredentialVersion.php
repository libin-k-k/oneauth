<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Cache;

class CredentialVersion
{
    public static function bump(mixed $user): void
    {
        Cache::forever(self::key($user), (string) time());
    }

    public static function current(mixed $user): string
    {
        return (string) Cache::get(self::key($user), '0');
    }

    public static function matches(mixed $user): bool
    {
        if (!request()->hasSession()) {
            return true;
        }

        return (string) session('oneauth.credentials_version', '0') === self::current($user);
    }

    public static function storeInSession(mixed $user): void
    {
        if (request()->hasSession()) {
            session(['oneauth.credentials_version' => self::current($user)]);
        }
    }

    protected static function key(mixed $user): string
    {
        return 'oneauth.credentials_version:' . $user::class . ':' . $user->getKey();
    }
}
