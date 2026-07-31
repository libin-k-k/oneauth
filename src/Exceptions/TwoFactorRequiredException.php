<?php

namespace Libinkk\OneAuth\Exceptions;

class TwoFactorRequiredException extends OneAuthException
{
    public function __construct(
        string $message = 'Two-factor verification is required.',
        protected ?string $challengeToken = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getChallengeToken(): ?string
    {
        return $this->challengeToken;
    }

    public function status(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'TWO_FACTOR_REQUIRED';
    }

    public function context(): array
    {
        return array_filter([
            'challenge_token' => $this->challengeToken,
            'two_factor_required' => true,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
