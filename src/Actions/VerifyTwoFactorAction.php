<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\TwoFactorEnabled;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;
use Libinkk\OneAuth\Support\TwoFactorCodeVerifier;

class VerifyTwoFactorAction
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

        $pending = TwoFactor::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('enabled', false)
            ->exists();

        $twoFactor = $this->verifier->verifyOrFail(
            $user,
            $payload,
            enabledOnly: false,
            allowRecovery: !$pending
        );

        if (!$twoFactor->enabled) {
            $twoFactor->update([
                'enabled' => true,
                'enabled_at' => now(),
            ]);
            Event::dispatch(new TwoFactorEnabled($user));
        }

        if (request()->hasSession()) {
            session(['oneauth.twofactor_verified' => true]);
        }

        return true;
    }
}
