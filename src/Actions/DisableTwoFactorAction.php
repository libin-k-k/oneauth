<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Events\TwoFactorDisabled;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;
use Libinkk\OneAuth\Support\TwoFactorCodeVerifier;

class DisableTwoFactorAction
{
    public function __construct(private TwoFactorCodeVerifier $verifier)
    {
    }

    public function execute(array $payload): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $password = (string) ($payload['password'] ?? '');
        $code = (string) ($payload['code'] ?? '');
        $recovery = (string) ($payload['recovery_code'] ?? '');

        if ($password === '' && $code === '' && $recovery === '') {
            throw new AuthenticationException('Password or two-factor code is required to disable 2FA.');
        }

        if ($password !== '') {
            if (!Hash::check($password, (string) $user->password)) {
                throw new AuthenticationException('Current password is invalid.');
            }
        } else {
            $this->verifier->verifyOrFail($user, $payload);
        }

        TwoFactor::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->update([
                'enabled' => false,
                'secret_encrypted' => null,
                'recovery_codes' => null,
            ]);

        Event::dispatch(new TwoFactorDisabled($user));

        return true;
    }
}
