<?php

namespace Libinkk\OneAuth\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Contracts\SessionRepositoryInterface;
use Libinkk\OneAuth\Models\Session;

class SessionRepository implements SessionRepositoryInterface
{
    public function createForUser(mixed $user, array $payload = []): void
    {
        Session::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'session_id' => $payload['session_id'] ?? Str::uuid()->toString(),
            'ip_address' => $payload['ip_address'] ?? request()->ip(),
            'user_agent' => $payload['user_agent'] ?? (string) request()->userAgent(),
            'last_activity_at' => now(),
            'expires_at' => now()->addSeconds((int) config('oneauth.session.idle_timeout_seconds', 7200)),
        ]);
    }

    public function revokeCurrent(mixed $user, ?string $sessionId = null): void
    {
        Session::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->delete();
    }

    public function forUser(mixed $user): array
    {
        return Session::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->latest('last_activity_at')
            ->get()
            ->toArray();
    }

    public function cleanupExpired(): int
    {
        return Session::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
