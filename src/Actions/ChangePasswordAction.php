<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Events\PasswordChanged;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\CredentialRevoker;
use Libinkk\OneAuth\Support\PasswordPolicy;

class ChangePasswordAction
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private CredentialRevoker $credentialRevoker
    ) {
    }

    public function execute(array $payload): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $current = (string) ($payload['current_password'] ?? '');
        if (!Hash::check($current, (string) $user->password)) {
            throw new AuthenticationException('Current password is invalid.');
        }

        $newPassword = (string) ($payload['new_password'] ?? '');
        $this->passwordPolicy->validate($newPassword);
        $this->passwordPolicy->assertNotReused($user, $newPassword);

        $user->forceFill(['password' => Hash::make($newPassword)])->save();
        $this->passwordPolicy->recordHistory($user);
        $this->credentialRevoker->revokeAll($user);

        Event::dispatch(new PasswordChanged($user));

        return true;
    }
}
