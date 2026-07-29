<?php

namespace Libinkk\OneAuth\Drivers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\JwtTokenService;
use Libinkk\OneAuth\Support\UserResolver;

class JwtDriver implements AuthenticationDriverInterface
{
    protected ?string $lastToken = null;

    public function __construct(private JwtTokenService $jwt)
    {
    }

    public function login(array $credentials): array
    {
        $identifier = (string) ($credentials['identifier'] ?? $credentials['email'] ?? $credentials['username'] ?? $credentials['phone'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $user = UserResolver::queryByIdentifiers($identifier);

        if (!$user || !Hash::check($password, (string) $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $accessToken = $this->jwt->issueAccessToken($user);
        $refreshToken = $this->jwt->issueRefreshToken($user);
        $this->lastToken = $accessToken;
        Auth::setUser($user);

        return ['user' => $user, 'token' => $accessToken, 'refresh_token' => $refreshToken];
    }

    public function logout(): void
    {
        Auth::logout();
    }

    public function refresh(): array
    {
        $refreshToken = (string) request()->input('refresh_token', '');
        $existing = $this->jwt->rotateRefreshToken($refreshToken);
        if (!$existing) {
            throw new AuthenticationException('Invalid refresh token.');
        }

        $userClass = $existing->authenticatable_type;
        $user = $userClass::query()->find($existing->authenticatable_id);
        if (!$user) {
            throw new AuthenticationException('User not found for refresh token.');
        }

        $accessToken = $this->jwt->issueAccessToken($user);
        $newRefreshToken = $this->jwt->issueRefreshToken($user);
        $this->lastToken = $accessToken;
        Auth::setUser($user);

        return ['user' => $user, 'token' => $accessToken, 'refresh_token' => $newRefreshToken];
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
