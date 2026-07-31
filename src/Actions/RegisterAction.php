<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Events\UserRegistered;
use Libinkk\OneAuth\Support\PasswordPolicy;

class RegisterAction
{
    public function __construct(private PasswordPolicy $passwordPolicy)
    {
    }

    public function execute(array $payload): array
    {
        $password = (string) ($payload['password'] ?? '');
        $this->passwordPolicy->validate($password);

        $modelClass = oneauth_user_model();
        $identifierFields = (array) config('oneauth.identifier_fields', ['email']);

        $attributes = [
            'name' => (string) ($payload['name'] ?? 'User'),
            'password' => Hash::make($password),
            'remember_token' => Str::random(10),
        ];

        foreach ($identifierFields as $field) {
            if (array_key_exists($field, $payload)) {
                $attributes[$field] = $payload[$field];
            }
        }

        $user = $modelClass::query()->create($attributes);
        $this->passwordPolicy->recordHistory($user);
        Event::dispatch(new UserRegistered($user));

        return ['user' => $user];
    }
}
