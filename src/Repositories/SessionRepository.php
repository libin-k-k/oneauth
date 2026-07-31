<?php

namespace Libinkk\OneAuth\Repositories;

use Illuminate\Support\Str;
use Libinkk\OneAuth\Contracts\SessionRepositoryInterface;
use Libinkk\OneAuth\Models\Session;

class SessionRepository implements SessionRepositoryInterface
{
    public function createForUser(mixed $user, array $payload = []): void
    {
        $sessionId = $payload['session_id']
            ?? (request()->hasSession() ? request()->session()->getId() : null)
            ?? Str::uuid()->toString();

        Session::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'session_id' => $sessionId,
            'ip_address' => $payload['ip_address'] ?? request()->ip(),
            'user_agent' => $payload['user_agent'] ?? (string) request()->userAgent(),
            'last_activity_at' => now(),
            'expires_at' => now()->addSeconds((int) config('oneauth.session.idle_timeout_seconds', 7200)),
        ]);

        if (request()->hasSession()) {
            session(['oneauth.tracking_session_id' => $sessionId]);
        }
    }

    public function revokeCurrent(mixed $user, ?string $sessionId = null): void
    {
        $sessionId ??= request()->hasSession()
            ? (session('oneauth.tracking_session_id') ?? request()->session()->getId())
            : null;

        $query = Session::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey());

        if ($sessionId) {
            $query->where('session_id', $sessionId)->delete();

            return;
        }

        $latest = (clone $query)->latest('last_activity_at')->first();
        if ($latest) {
            $latest->delete();
        }
    }

    public function revokeAll(mixed $user): void
    {
        Session::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
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
