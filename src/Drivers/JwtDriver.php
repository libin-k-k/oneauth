<?php

namespace Libinkk\OneAuth\Drivers;

use Illuminate\Support\Facades\Auth;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\JwtTokenService;
use Libinkk\OneAuth\Support\PasswordVerifier;
use Libinkk\OneAuth\Support\UserResolver;

class JwtDriver implements AuthenticationDriverInterface
{
    protected ?string $lastToken = null;

    public function __construct(private JwtTokenService $jwt)
    {
    }

    public function attempt(array $credentials): mixed
    {
        $identifier = (string) ($credentials['identifier'] ?? $credentials['email'] ?? $credentials['username'] ?? $credentials['phone'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $user = UserResolver::queryByIdentifiers($identifier);
        PasswordVerifier::assertValid($user, $password);

        return $user;
    }

    public function establish(mixed $user): array
    {
        $accessToken = $this->jwt->issueAccessToken($user);
        $refreshToken = $this->jwt->issueRefreshToken($user);
        $this->lastToken = $accessToken;
        Auth::setUser($user);

        return ['user' => $user, 'token' => $accessToken, 'refresh_token' => $refreshToken];
    }

    public function login(array $credentials): array
    {
        return $this->establish($this->attempt($credentials));
    }

    public function logout(): void
    {
        $user = $this->user();
        if ($user) {
            $this->jwt->revokeAllForUser($user);
        }

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

    public function authenticateBearer(?string $bearerToken): bool
    {
        if ($bearerToken === null || $bearerToken === '') {
            return false;
        }

        try {
            $payload = $this->jwt->decodeAccessToken($bearerToken);
            $userClass = oneauth_user_model();
            $user = $userClass::query()->find($payload->sub ?? null);
            if (!$user) {
                return false;
            }

            Auth::setUser($user);
            $this->lastToken = $bearerToken;

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
