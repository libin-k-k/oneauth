<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TwoFactorChallengeService
{
    public function issue(mixed $user, string $identifier = ''): string
    {
        $token = Str::random(64);
        $ttl = (int) config('oneauth.two_factor.challenge_ttl_seconds', 300);

        Cache::put($this->cacheKey($token), [
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'identifier' => $identifier,
        ], $ttl);

        return $token;
    }

    public function peek(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $payload = Cache::get($this->cacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    public function consume(string $token): void
    {
        Cache::forget($this->cacheKey($token));
    }

    public function resolveUser(string $token, bool $consume = false): mixed
    {
        $payload = $consume ? Cache::pull($this->cacheKey($token)) : $this->peek($token);
        if (!is_array($payload)) {
            return null;
        }

        $userClass = $payload['authenticatable_type'] ?? null;
        $userId = $payload['authenticatable_id'] ?? null;

        if (!is_string($userClass) || $userId === null || !class_exists($userClass)) {
            return null;
        }

        return $userClass::query()->find($userId);
    }

    protected function cacheKey(string $token): string
    {
        return 'oneauth.2fa.challenge:' . hash('sha256', $token);
    }
}
