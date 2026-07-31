<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Libinkk\OneAuth\Events\PasswordReset;
use Libinkk\OneAuth\Support\CredentialRevoker;
use Libinkk\OneAuth\Support\PasswordPolicy;
use Libinkk\OneAuth\Support\UserResolver;

class ResetPasswordAction
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private CredentialRevoker $credentialRevoker
    ) {
    }

    public function execute(array $payload): string
    {
        $password = (string) ($payload['password'] ?? '');
        $this->passwordPolicy->validate($password);

        $email = (string) ($payload['email'] ?? '');
        $existing = UserResolver::queryByIdentifiers($email);
        if ($existing) {
            $this->passwordPolicy->assertNotReused($existing, $password);
        }

        return Password::reset(
            [
                'email' => $email,
                'token' => (string) ($payload['token'] ?? ''),
                'password' => $password,
                'password_confirmation' => (string) ($payload['password_confirmation'] ?? $password),
            ],
            function ($user) use ($password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $this->passwordPolicy->recordHistory($user);
                $this->credentialRevoker->revokeAll($user);
                Event::dispatch(new PasswordReset($user));
            }
        );
    }
}
