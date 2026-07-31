<?php

namespace Libinkk\OneAuth\Repositories;

use Libinkk\OneAuth\Contracts\DeviceRepositoryInterface;
use Libinkk\OneAuth\Models\Device;
use Libinkk\OneAuth\Support\AccessPolicy;
use Libinkk\OneAuth\Support\UserAgentParser;

class DeviceRepository implements DeviceRepositoryInterface
{
    public function __construct(
        private UserAgentParser $parser,
        private AccessPolicy $accessPolicy
    ) {
    }

    public function recordForUser(mixed $user, array $deviceData = []): Device
    {
        $ua = (string) ($deviceData['user_agent'] ?? request()->userAgent());
        $parsed = (bool) config('oneauth.devices.parse_user_agent', true)
            ? $this->parser->parse($ua)
            : ['browser' => null, 'os' => null, 'device_name' => 'Unknown Device'];

        $fingerprint = $deviceData['fingerprint'] ?? sha1($ua . '|' . request()->ip());

        $device = Device::query()->firstOrNew([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'fingerprint' => $fingerprint,
        ]);

        $wasNew = !$device->exists;

        if ($wasNew) {
            $device->first_login_at = $deviceData['first_login_at'] ?? now();
            $device->trusted = (bool) ($deviceData['trusted'] ?? false);
        }

        $device->fill([
            'device_name' => $deviceData['device_name'] ?? ($device->device_name ?: $parsed['device_name']),
            'browser' => $deviceData['browser'] ?? $parsed['browser'] ?? $device->browser,
            'os' => $deviceData['os'] ?? $parsed['os'] ?? $device->os,
            'ip_address' => $deviceData['ip_address'] ?? request()->ip(),
            'country' => $deviceData['country']
                ?? ($this->accessPolicy->requestCountry() ?: $device->country),
            'timezone' => $deviceData['timezone']
                ?? config('oneauth.devices.default_timezone')
                ?? $device->timezone,
            'last_login_at' => now(),
        ]);

        $device->save();
        $device->wasRecentlyCreated = $wasNew;

        return $device;
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
        $device = $this->findByFingerprint($user, $fingerprint);
        if (!$device) {
            return false;
        }

        $device->update(['trusted' => $trusted]);

        return true;
    }

    public function isTrusted(mixed $user, ?string $fingerprint = null): bool
    {
        $fingerprint ??= sha1((string) request()->userAgent() . '|' . request()->ip());
        $device = $this->findByFingerprint($user, $fingerprint);

        return $device?->trusted === true;
    }

    public function findByFingerprint(mixed $user, string $fingerprint): ?Device
    {
        return Device::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('fingerprint', $fingerprint)
            ->first();
    }
}
