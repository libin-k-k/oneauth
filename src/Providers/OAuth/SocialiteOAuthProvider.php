<?php

namespace Libinkk\OneAuth\Providers\OAuth;

use Libinkk\OneAuth\Contracts\OAuthProviderInterface;
use Libinkk\OneAuth\Exceptions\OneAuthException;

class SocialiteOAuthProvider implements OAuthProviderInterface
{
    public function __construct(private string $driver)
    {
    }

    public function provider(): string
    {
        return $this->driver;
    }

    public function userFromToken(string $token): array
    {
        if (!class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
            throw new OneAuthException('Socialite is not installed.');
        }

        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($this->driver)->userFromToken($token);
        } catch (\Throwable $throwable) {
            throw new OneAuthException(
                'Unable to resolve social user for provider "' . $this->driver . '": ' . $throwable->getMessage(),
                previous: $throwable
            );
        }

        return [
            'provider_id' => (string) $socialUser->getId(),
            'email' => $socialUser->getEmail(),
            'name' => $socialUser->getName(),
            'meta' => [
                'nickname' => $socialUser->getNickname(),
                'avatar' => $socialUser->getAvatar(),
            ],
        ];
    }
}
