<?php

namespace Libinkk\OneAuth\Drivers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Exceptions\OneAuthException;
use Libinkk\OneAuth\Support\UserResolver;

class SanctumDriver implements AuthenticationDriverInterface
{
    protected ?string $lastToken = null;

    public function login(array $credentials): array
    {
        if (!class_exists(\Laravel\Sanctum\HasApiTokens::class)) {
            throw new OneAuthException('Sanctum is not installed.');
        }

        $identifier = (string) ($credentials['identifier'] ?? $credentials['email'] ?? $credentials['username'] ?? $credentials['phone'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $user = UserResolver::queryByIdentifiers($identifier);

        if (!$user || !Hash::check($password, (string) $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $token = $user->createToken('oneauth')->plainTextToken;
        $this->lastToken = $token;
        Auth::setUser($user);

        return ['user' => $user, 'token' => $token, 'refresh_token' => null];
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

        $token = $user->createToken('oneauth')->plainTextToken;
        $this->lastToken = $token;

        return ['user' => $user, 'token' => $token, 'refresh_token' => null];
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
