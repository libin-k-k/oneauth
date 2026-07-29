<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Events\SocialLogin;
use Libinkk\OneAuth\Exceptions\OneAuthException;
use Libinkk\OneAuth\Models\SocialAccount;

class SocialLoginAction
{
    public function execute(string $provider, array $payload): array
    {
        if (!in_array($provider, ['google', 'apple'], true)) {
            throw new OneAuthException('Unsupported social provider: ' . $provider);
        }

        $providerDriver = app('oneauth.oauth.' . $provider);
        $social = $providerDriver->userFromToken((string) ($payload['token'] ?? ''));
        $userModel = oneauth_user_model();

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $social['provider_id'])
            ->first();

        $user = $account?->authenticatable;
        if (!$user && !empty($social['email'])) {
            $user = $userModel::query()->where('email', $social['email'])->first();
        }

        if (!$user && (bool) config('oneauth.social.create_user_if_missing', true)) {
            $user = $userModel::query()->create([
                'name' => $social['name'] ?? ucfirst($provider) . ' User',
                'email' => $social['email'] ?? null,
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ]);
        }

        if (!$user) {
            throw new OneAuthException('Unable to resolve a user for social login.');
        }

        SocialAccount::query()->updateOrCreate(
            ['provider' => $provider, 'provider_id' => $social['provider_id']],
            [
                'authenticatable_type' => $user::class,
                'authenticatable_id' => $user->getKey(),
                'email' => $social['email'] ?? null,
                'name' => $social['name'] ?? null,
                'meta' => $social['meta'] ?? [],
            ]
        );

        Auth::setUser($user);
        Event::dispatch(new SocialLogin($user, ['provider' => $provider]));

        return app(\Libinkk\OneAuth\OneAuthManager::class)->driver()->refresh();
    }
}
