<?php

namespace Libinkk\OneAuth\Contracts;

interface DeviceRepositoryInterface
{
    public function recordForUser(mixed $user, array $deviceData = []): mixed;

    public function forUser(mixed $user): array;

    public function markTrusted(mixed $user, string $fingerprint, bool $trusted = true): bool;

    public function isTrusted(mixed $user, ?string $fingerprint = null): bool;

    public function findByFingerprint(mixed $user, string $fingerprint): mixed;
}
