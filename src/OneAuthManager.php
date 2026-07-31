<?php

namespace Libinkk\OneAuth;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Contracts\AuthenticationDriverInterface;
use Libinkk\OneAuth\Contracts\DeviceRepositoryInterface;
use Libinkk\OneAuth\Contracts\SessionRepositoryInterface;
use Libinkk\OneAuth\Drivers\JwtDriver;
use Libinkk\OneAuth\Drivers\SanctumDriver;
use Libinkk\OneAuth\Drivers\SessionDriver;
use Libinkk\OneAuth\Events\UserLoggedOut;
use Libinkk\OneAuth\Exceptions\OneAuthException;

class OneAuthManager
{
    public function __construct(private Container $container)
    {
    }

    public function driver(?string $name = null): AuthenticationDriverInterface
    {
        $name ??= (string) config('oneauth.driver', 'session');

        return match ($name) {
            'session' => $this->container->make(SessionDriver::class),
            'sanctum' => $this->container->make(SanctumDriver::class),
            'jwt' => $this->container->make(JwtDriver::class),
            default => throw new OneAuthException('Unsupported OneAuth driver: ' . $name),
        };
    }

    public function register(array $payload): array
    {
        return app(Actions\RegisterAction::class)->execute($payload);
    }

    public function login(array $payload): array
    {
        return app(Actions\LoginAction::class)->execute($payload);
    }

    public function logout(): void
    {
        $user = $this->driver()->user();
        if ($user) {
            app(SessionRepositoryInterface::class)->revokeCurrent($user);
        }
        $this->driver()->logout();
        if ($user) {
            Event::dispatch(new UserLoggedOut($user));
        }
    }

    public function user(): mixed
    {
        return $this->driver()->user();
    }

    public function refresh(): array
    {
        return $this->driver()->refresh();
    }

    public function verifyEmail(array $payload): bool
    {
        return app(Actions\VerifyEmailAction::class)->execute($payload);
    }

    public function sendEmailVerification(array $payload = []): array
    {
        return app(Actions\SendEmailVerificationAction::class)->execute($payload);
    }

    public function sendOtp(array $payload): array
    {
        return app(Actions\SendOtpAction::class)->execute($payload);
    }

    public function verifyOtp(array $payload): bool
    {
        return app(Actions\VerifyOtpAction::class)->execute($payload);
    }

    public function enableTwoFactor(array $payload): array
    {
        return app(Actions\EnableTwoFactorAction::class)->execute($payload);
    }

    public function disableTwoFactor(array $payload): bool
    {
        return app(Actions\DisableTwoFactorAction::class)->execute($payload);
    }

    public function verifyTwoFactor(array $payload): bool
    {
        return app(Actions\VerifyTwoFactorAction::class)->execute($payload);
    }

    public function completeTwoFactorLogin(array $payload): array
    {
        return app(Actions\CompleteTwoFactorLoginAction::class)->execute($payload);
    }

    public function socialLogin(string $provider, array $payload): array
    {
        return app(Actions\SocialLoginAction::class)->execute($provider, $payload);
    }

    public function forgotPassword(array $payload): string
    {
        return app(Actions\RequestPasswordResetAction::class)->execute($payload);
    }

    public function resetPassword(array $payload): string
    {
        return app(Actions\ResetPasswordAction::class)->execute($payload);
    }

    public function changePassword(array $payload): bool
    {
        return app(Actions\ChangePasswordAction::class)->execute($payload);
    }

    public function sessions(): array
    {
        $user = $this->driver()->user();
        return app(SessionRepositoryInterface::class)->forUser($user);
    }

    public function devices(): array
    {
        $user = $this->driver()->user();
        return app(DeviceRepositoryInterface::class)->forUser($user);
    }

    public function trustDevice(string $fingerprint, bool $trusted = true): bool
    {
        $user = $this->driver()->user();
        if (!$user) {
            throw new Exceptions\AuthenticationException('User is not authenticated.');
        }

        return app(DeviceRepositoryInterface::class)->markTrusted($user, $fingerprint, $trusted);
    }

    public function revokeSession(string $sessionId): bool
    {
        $user = $this->driver()->user();
        if (!$user) {
            throw new Exceptions\AuthenticationException('User is not authenticated.');
        }

        return app(SessionRepositoryInterface::class)->revokeBySessionId($user, $sessionId);
    }

    public function logoutOtherSessions(): int
    {
        $user = $this->driver()->user();
        if (!$user) {
            throw new Exceptions\AuthenticationException('User is not authenticated.');
        }

        return app(SessionRepositoryInterface::class)->revokeOthers($user);
    }

    public function anonymousLogin(array $payload = []): array
    {
        return app(Actions\AnonymousLoginAction::class)->execute($payload);
    }

    public function loginWithOtp(array $payload): array
    {
        return app(Actions\LoginWithOtpAction::class)->execute($payload);
    }

    public function lockAccount(string $identifier, ?int $seconds = null, string $reason = 'manual'): void
    {
        app(Support\AccountLockService::class)->lock($identifier, $seconds, $reason, $this->driver()->user());
    }

    public function unlockAccount(string $identifier): bool
    {
        return app(Support\AccountLockService::class)->unlock($identifier);
    }
}
