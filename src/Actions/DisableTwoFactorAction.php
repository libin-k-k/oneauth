<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\TwoFactorDisabled;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;

class DisableTwoFactorAction
{
    public function execute(array $payload): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
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
