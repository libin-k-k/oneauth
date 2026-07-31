<?php

namespace Libinkk\OneAuth\Repositories;

use Libinkk\OneAuth\Contracts\DeviceRepositoryInterface;
use Libinkk\OneAuth\Models\Device;

class DeviceRepository implements DeviceRepositoryInterface
{
    public function recordForUser(mixed $user, array $deviceData = []): void
    {
        $fingerprint = $deviceData['fingerprint'] ?? sha1((string) request()->userAgent() . '|' . request()->ip());

        $device = Device::query()->firstOrNew([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'fingerprint' => $fingerprint,
        ]);

        if (!$device->exists) {
            $device->first_login_at = $deviceData['first_login_at'] ?? now();
            $device->trusted = (bool) ($deviceData['trusted'] ?? false);
        }

        $device->fill([
            'device_name' => $deviceData['device_name'] ?? ($device->device_name ?: 'Unknown Device'),
            'browser' => $deviceData['browser'] ?? $device->browser,
            'os' => $deviceData['os'] ?? $device->os,
            'ip_address' => $deviceData['ip_address'] ?? request()->ip(),
            'country' => $deviceData['country'] ?? $device->country,
            'timezone' => $deviceData['timezone'] ?? $device->timezone,
            'last_login_at' => now(),
        ]);

        $device->save();
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

    public function markTrusted(mixed $user, string $fingerprint, bool $trusted = true): bool
    {
        $device = Device::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->first();

        if (!$device) {
            return false;
        }

        $device->update(['trusted' => $trusted]);

        return true;
    }
}
