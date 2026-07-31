<?php

namespace Libinkk\OneAuth\Drivers;

use Illuminate\Support\Facades\Auth;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Exceptions\OneAuthException;
use Libinkk\OneAuth\Support\PasswordVerifier;
use Libinkk\OneAuth\Support\UserResolver;

class SanctumDriver implements AuthenticationDriverInterface
{
    protected ?string $lastToken = null;

    public function attempt(array $credentials): mixed
    {
        if (!class_exists(\Laravel\Sanctum\HasApiTokens::class)) {
            throw new OneAuthException('Sanctum is not installed.');
        }

        $identifier = (string) ($credentials['identifier'] ?? $credentials['email'] ?? $credentials['username'] ?? $credentials['phone'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $user = UserResolver::queryByIdentifiers($identifier);
        PasswordVerifier::assertValid($user, $password);

        return $user;
    }

    public function establish(mixed $user): array
    {
        if (!method_exists($user, 'createToken')) {
            throw new OneAuthException('Sanctum is not installed.');
        }

        $token = $user->createToken('oneauth')->plainTextToken;
        $this->lastToken = $token;
        Auth::setUser($user);

        return ['user' => $user, 'token' => $token, 'refresh_token' => null];
    }

    public function login(array $credentials): array
    {
        return $this->establish($this->attempt($credentials));
    }

    public function logout(): void
    {
        $user = $this->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
        Auth::logout();
    }

    public function refresh(): array
    {
        $user = $this->user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        } elseif (method_exists($user, 'tokens')) {
            $user->tokens()->where('name', 'oneauth')->delete();
        }

        return $this->establish($user);
    }

    public function user(): mixed
    {
        return Auth::user();
    }

    public function check(): bool
    {
        return Auth::check();
    }

    public function guest(): bool
    {
        return Auth::guest();
    }

    public function token(): ?string
    {
        return $this->lastToken;
    }
}
