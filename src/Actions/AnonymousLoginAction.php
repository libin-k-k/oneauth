<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Events\UserRegistered;
use Libinkk\OneAuth\Exceptions\OneAuthException;
use Libinkk\OneAuth\Pipelines\LoginPipeline;

class AnonymousLoginAction
{
    public function __construct(private LoginPipeline $pipeline)
    {
    }

    public function execute(array $payload = []): array
    {
        if (!(bool) config('oneauth.anonymous.enabled', false)) {
            throw new OneAuthException('Anonymous login is disabled.');
        }

        $modelClass = oneauth_user_model();
        $prefix = (string) config('oneauth.anonymous.name_prefix', 'Guest');
        $token = Str::lower(Str::random(12));

        $attributes = [
            'name' => $prefix . ' ' . Str::upper(Str::random(4)),
            'password' => Hash::make(Str::random(40)),
            'remember_token' => Str::random(10),
        ];

        $identifierFields = (array) config('oneauth.identifier_fields', ['email']);
        if (in_array('username', $identifierFields, true) || !in_array('email', $identifierFields, true)) {
            $attributes['username'] = 'guest_' . $token;
        }
        if (in_array('email', $identifierFields, true)) {
            $attributes['email'] = 'guest_' . $token . '@oneauth.local';
        }

        foreach ($payload as $key => $value) {
            if (in_array($key, ['name', 'username', 'email', 'phone'], true) && $value !== null && $value !== '') {
                $attributes[$key] = $value;
            }
        }

        $user = $modelClass::query()->create($attributes);
        Event::dispatch(new UserRegistered($user, ['anonymous' => true]));

        return $this->pipeline->authenticateResolvedUser($user, (string) ($user->email ?? $user->username ?? $user->getKey()));
    }
}
