<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Exceptions\AuthenticationException;

class PasswordVerifier
{
    /**
     * Always run a password hash check to reduce user-enumeration timing differences.
     */
    public static function assertValid(?object $user, string $password): void
    {
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $hash = ($user && isset($user->password) && (string) $user->password !== '')
            ? (string) $user->password
            : $dummy;

        $valid = $user !== null && Hash::check($password, $hash);

        if (!$valid) {
            if ($user === null) {
                Hash::check($password, $dummy);
            }

            throw new AuthenticationException('Invalid credentials.');
        }
    }
}
