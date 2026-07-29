<?php

namespace Libinkk\OneAuth\Contracts;

interface DeviceRepositoryInterface
{
    public function recordForUser(mixed $user, array $deviceData = []): void;

    public function forUser(mixed $user): array;
}
