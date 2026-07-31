<?php

namespace Libinkk\OneAuth\Actions;

use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Pipelines\LoginPipeline;
use Libinkk\OneAuth\Support\LoginRateLimiter;
use Libinkk\OneAuth\Support\OtpService;
use Libinkk\OneAuth\Support\UserResolver;

class LoginWithOtpAction
{
    public function __construct(
        private OtpService $otp,
        private LoginPipeline $pipeline,
        private LoginRateLimiter $rateLimiter
    ) {
    }

    public function execute(array $payload): array
    {
        $identifier = (string) (
            $payload['identifier']
            ?? $payload['email']
            ?? $payload['username']
            ?? $payload['phone']
            ?? $payload['target']
            ?? ''
        );
        $code = (string) ($payload['code'] ?? '');
        $ip = (string) request()->ip();

        if ($identifier === '' || $code === '') {
            throw new AuthenticationException('Identifier and OTP code are required.');
        }

        $this->rateLimiter->ensureNotLocked($identifier, $ip);

        $user = UserResolver::queryByIdentifiers($identifier);
        if (!$user) {
            $this->rateLimiter->hit($identifier, $ip);
            throw new AuthenticationException('Invalid credentials.');
        }

        $target = (string) ($payload['target'] ?? $user->email ?? $user->phone ?? $identifier);

        try {
            $this->otp->verify($user, 'login', $target, $code);
        } catch (\Throwable $throwable) {
            $this->rateLimiter->hit($identifier, $ip);
            throw $throwable;
        }

        $this->rateLimiter->clear($identifier, $ip);

        return $this->pipeline->authenticateResolvedUser($user, $identifier);
    }
}
