<?php

namespace Libinkk\OneAuth\Support;

class UserAgentParser
{
    public function parse(?string $userAgent): array
    {
        $userAgent = (string) $userAgent;

        return [
            'browser' => $this->browser($userAgent),
            'os' => $this->os($userAgent),
            'device_name' => $this->deviceName($userAgent),
        ];
    }

    protected function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') && !str_contains($ua, 'Edg/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome/') => 'Safari',
            str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/') => 'IE',
            default => 'Unknown Browser',
        };
    }

    protected function os(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };
    }

    protected function deviceName(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android Device',
            str_contains($ua, 'Mobile') => 'Mobile Device',
            default => 'Desktop',
        };
    }
}
