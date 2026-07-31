<?php

namespace Libinkk\OneAuth\Pipelines;

use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\UserLoggedIn;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Exceptions\TwoFactorRequiredException;
use Libinkk\OneAuth\Models\LoginAttempt;
use Libinkk\OneAuth\Models\TwoFactor;
use Libinkk\OneAuth\OneAuthManager;
use Libinkk\OneAuth\Repositories\DeviceRepository;
use Libinkk\OneAuth\Repositories\SessionRepository;
use Libinkk\OneAuth\Support\CredentialVersion;
use Libinkk\OneAuth\Support\EmailVerificationStatus;
use Libinkk\OneAuth\Support\LoginRateLimiter;
use Libinkk\OneAuth\Support\TwoFactorChallengeService;

class LoginPipeline
{
    public function __construct(
        private OneAuthManager $manager,
        private LoginRateLimiter $rateLimiter,
        private SessionRepository $sessions,
        private DeviceRepository $devices,
        private TwoFactorChallengeService $challenges
    ) {
    }

    public function handle(array $payload): array
    {
        $identifier = (string) ($payload['identifier'] ?? $payload['email'] ?? $payload['username'] ?? $payload['phone'] ?? '');
        $ip = (string) request()->ip();
        $this->rateLimiter->ensureNotLocked($identifier, $ip);

        try {
            $user = $this->manager->driver()->attempt($payload);
            $result = $this->authenticateResolvedUser($user, $identifier);
            $this->rateLimiter->clear($identifier, $ip);

            return $result;
        } catch (TwoFactorRequiredException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            $this->rateLimiter->hit($identifier, $ip);
            LoginAttempt::query()->create([
                'identifier' => $identifier,
                'ip_address' => $ip,
                'successful' => false,
                'attempted_at' => now(),
            ]);
            throw $throwable;
        }
    }

    public function authenticateResolvedUser(mixed $user, string $identifier = ''): array
    {
        if ((bool) config('oneauth.security.require_verified_email', false) && !EmailVerificationStatus::isVerified($user)) {
            throw new AuthenticationException('Email verification is required.');
        }

        $twoFactor = TwoFactor::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('enabled', true)
            ->first();

        if ($twoFactor) {
            $challengeToken = $this->challenges->issue($user, $identifier);
            throw new TwoFactorRequiredException('Two-factor verification is required.', $challengeToken);
        }

        return $this->completeLogin($user, $identifier);
    }

    public function completeLogin(mixed $user, string $identifier = ''): array
    {
        $result = $this->manager->driver()->establish($user);
        CredentialVersion::storeInSession($user);

        $this->sessions->createForUser($user);
        $this->devices->recordForUser($user);
        LoginAttempt::query()->create([
            'identifier' => $identifier !== '' ? $identifier : (string) ($user->email ?? $user->getKey()),
            'ip_address' => (string) request()->ip(),
            'successful' => true,
            'attempted_at' => now(),
        ]);
        Event::dispatch(new UserLoggedIn($user));

        return $result;
    }
}
