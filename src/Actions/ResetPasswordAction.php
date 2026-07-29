<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Libinkk\OneAuth\Events\PasswordReset;
use Libinkk\OneAuth\Models\PasswordHistory;
use Libinkk\OneAuth\Support\PasswordPolicy;

class ResetPasswordAction
{
    public function __construct(private PasswordPolicy $passwordPolicy)
    {
    }

    public function execute(array $payload): string
    {
        $password = (string) ($payload['password'] ?? '');
        $this->passwordPolicy->validate($password);

        return Password::reset(
            [
                'email' => (string) ($payload['email'] ?? ''),
                'token' => (string) ($payload['token'] ?? ''),
                'password' => $password,
                'password_confirmation' => (string) ($payload['password_confirmation'] ?? $password),
            ],
            function ($user) use ($password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                PasswordHistory::query()->create([
                    'authenticatable_type' => $user::class,
                    'authenticatable_id' => $user->getKey(),
                    'password_hash' => $user->password,
                    'changed_at' => now(),
                ]);
                Event::dispatch(new PasswordReset($user));
            }
        );
    }
}
