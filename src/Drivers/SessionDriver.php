<?php

namespace Libinkk\OneAuth\Drivers;

use Illuminate\Support\Facades\Auth;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Support\CredentialVersion;
use Libinkk\OneAuth\Support\PasswordVerifier;
use Libinkk\OneAuth\Support\UserResolver;

class SessionDriver implements AuthenticationDriverInterface
{
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
        Auth::login($user);
        CredentialVersion::storeInSession($user);

        if (request()->hasSession()) {
            request()->session()->regenerate();
            CredentialVersion::storeInSession($user);
            if (session()->has('oneauth.tracking_session_id')) {
                // Regenerated session id should replace tracking id on next createForUser.
            }
        }

        return ['user' => $user, 'token' => null, 'refresh_token' => null];
    }

    public function login(array $credentials): array
    {
        return $this->establish($this->attempt($credentials));
    }

    public function logout(): void
    {
        Auth::logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }

    public function refresh(): array
    {
        return ['user' => $this->user(), 'token' => null, 'refresh_token' => null];
    }

    public function user(): mixed
    {
        $user = Auth::user();
        if ($user && !CredentialVersion::matches($user)) {
            $this->logout();

            return null;
        }

        return $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function token(): ?string
    {
        return null;
    }
}
