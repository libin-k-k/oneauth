<?php

namespace Libinkk\OneAuth\Drivers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\UserResolver;

class SessionDriver implements AuthenticationDriverInterface
{
    public function login(array $credentials): array
    {
        $identifier = (string) ($credentials['identifier'] ?? $credentials['email'] ?? $credentials['username'] ?? $credentials['phone'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $user = UserResolver::queryByIdentifiers($identifier);

        if (!$user || !Hash::check($password, (string) $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        Auth::login($user);
        request()->session()->regenerate();

        return ['user' => $user, 'token' => null, 'refresh_token' => null];
    }

    public function logout(): void
    {
        Auth::logout();
    }

    public function refresh(): array
    {
        return ['user' => $this->user(), 'token' => null, 'refresh_token' => null];
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
        return null;
    }
}
