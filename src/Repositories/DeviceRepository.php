<?php

namespace Libinkk\OneAuth\Repositories;

use Libinkk\OneAuth\Contracts\DeviceRepositoryInterface;
use Libinkk\OneAuth\Models\Device;

class DeviceRepository implements DeviceRepositoryInterface
{
    public function recordForUser(mixed $user, array $deviceData = []): void
    {
        $fingerprint = $deviceData['fingerprint'] ?? sha1((string) request()->userAgent() . '|' . request()->ip());

        Device::query()->updateOrCreate(
            [
                'authenticatable_type' => $user::class,
                'authenticatable_id' => $user->getKey(),
                'fingerprint' => $fingerprint,
            ],
            [
                'device_name' => $deviceData['device_name'] ?? 'Unknown Device',
                'browser' => $deviceData['browser'] ?? null,
                'os' => $deviceData['os'] ?? null,
                'ip_address' => $deviceData['ip_address'] ?? request()->ip(),
                'country' => $deviceData['country'] ?? null,
                'timezone' => $deviceData['timezone'] ?? null,
                'first_login_at' => $deviceData['first_login_at'] ?? now(),
                'last_login_at' => now(),
            ]
        );
    }

    public function forUser(mixed $user): array
    {
        return Device::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->latest('last_login_at')
            ->get()
            ->toArray();
    }
}
