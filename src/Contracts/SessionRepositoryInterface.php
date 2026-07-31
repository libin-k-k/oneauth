<?php

namespace Libinkk\OneAuth\Contracts;

interface SessionRepositoryInterface
{
    public function createForUser(mixed $user, array $payload = []): void;

    public function revokeCurrent(mixed $user, ?string $sessionId = null): void;

    public function revokeBySessionId(mixed $user, string $sessionId): bool;

    public function revokeOthers(mixed $user, ?string $currentSessionId = null): int;

    public function revokeAll(mixed $user): void;

    public function forUser(mixed $user): array;

    public function cleanupExpired(): int;
}
