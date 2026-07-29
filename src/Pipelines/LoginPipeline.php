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
use Libinkk\OneAuth\Support\LoginRateLimiter;

class LoginPipeline
{
    public function __construct(
        private OneAuthManager $manager,
        private LoginRateLimiter $rateLimiter,
        private SessionRepository $sessions,
        private DeviceRepository $devices
    ) {
    }

    public function handle(array $payload): array
    {
        $identifier = (string) ($payload['identifier'] ?? $payload['email'] ?? $payload['username'] ?? $payload['phone'] ?? '');
        $ip = (string) request()->ip();
        $this->rateLimiter->hitOrFail($identifier, $ip);

        try {
            $result = $this->manager->driver()->login($payload);
            $user = $result['user'];

            if ((bool) config('oneauth.security.require_verified_email', false) && isset($user->email_verified_at) && !$user->email_verified_at) {
                throw new AuthenticationException('Email verification is required.');
            }

            $twoFactor = TwoFactor::query()
                ->where('authenticatable_type', $user::class)
                ->where('authenticatable_id', $user->getKey())
                ->where('enabled', true)
                ->first();

            if ($twoFactor) {
                throw new TwoFactorRequiredException('Two-factor verification is required.');
            }

            $this->sessions->createForUser($user);
            $this->devices->recordForUser($user);
            LoginAttempt::query()->create([
                'identifier' => $identifier,
                'ip_address' => $ip,
                'successful' => true,
                'attempted_at' => now(),
            ]);
            Event::dispatch(new UserLoggedIn($user));
            return $result;
        } catch (\Throwable $throwable) {
            LoginAttempt::query()->create([
                'identifier' => $identifier,
                'ip_address' => $ip,
                'successful' => false,
                'attempted_at' => now(),
            ]);
            throw $throwable;
        }
    }
}
