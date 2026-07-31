<?php

namespace Libinkk\OneAuth\Contracts;

interface SessionRepositoryInterface
{
    public function createForUser(mixed $user, array $payload = []): void;

    public function revokeCurrent(mixed $user, ?string $sessionId = null): void;

    public function revokeAll(mixed $user): void;

    public function forUser(mixed $user): array;

    public function cleanupExpired(): int;
}
