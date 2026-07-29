<?php

namespace Libinkk\OneAuth\Providers\OAuth;

use Libinkk\OneAuth\Contracts\OAuthProviderInterface;
use Libinkk\OneAuth\Exceptions\OneAuthException;

class GoogleOAuthProvider implements OAuthProviderInterface
{
    public function provider(): string
    {
        return 'google';
    }

    public function userFromToken(string $token): array
    {
        if (!class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            throw new OneAuthException('Socialite is not installed.');
        }

        $socialUser = \Laravel\Socialite\Facades\Socialite::driver('google')->userFromToken($token);

        return [
            'provider_id' => (string) $socialUser->getId(),
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
            'meta' => ['nickname' => $socialUser->getNickname()],
        ];
    }
}
