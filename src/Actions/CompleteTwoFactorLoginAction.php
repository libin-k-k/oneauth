<?php

namespace Libinkk\OneAuth\Actions;

use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Pipelines\LoginPipeline;
use Libinkk\OneAuth\Support\LoginRateLimiter;
use Libinkk\OneAuth\Support\TwoFactorChallengeService;
use Libinkk\OneAuth\Support\TwoFactorCodeVerifier;

class CompleteTwoFactorLoginAction
{
    public function __construct(
        private TwoFactorChallengeService $challenges,
        private TwoFactorCodeVerifier $verifier,
        private LoginPipeline $pipeline,
        private LoginRateLimiter $rateLimiter
    ) {
    }

    public function execute(array $payload): array
    {
        $challengeToken = (string) ($payload['challenge_token'] ?? '');
        $challenge = $this->challenges->peek($challengeToken);
        $user = $this->challenges->resolveUser($challengeToken, consume: false);

        if (!$user || !is_array($challenge)) {
            throw new AuthenticationException('Invalid or expired two-factor challenge.');
        }

        $this->verifier->verifyOrFail($user, $payload);
        $this->challenges->consume($challengeToken);

        if (request()->hasSession()) {
            session(['oneauth.twofactor_verified' => true]);
        }

        $identifier = (string) ($payload['identifier'] ?? $challenge['identifier'] ?? $user->email ?? $user->getKey());
        $this->rateLimiter->clear($identifier, (string) request()->ip());

        return $this->pipeline->completeLogin($user, $identifier);
    }
}
