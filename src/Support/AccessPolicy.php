<?php

namespace Libinkk\OneAuth\Support;

use Libinkk\OneAuth\Exceptions\AuthenticationException;

class AccessPolicy
{
    public function assertRequestAllowed(): void
    {
        $ip = (string) request()->ip();
        $country = $this->requestCountry();

        $blockedIps = (array) config('oneauth.security.blocked_ips', []);
        if ($blockedIps !== [] && in_array($ip, $blockedIps, true)) {
            throw new AuthenticationException('Access from this IP address is blocked.');
        }

        $allowedIps = (array) config('oneauth.security.allowed_ips', []);
        if ($allowedIps !== [] && !in_array($ip, $allowedIps, true)) {
            throw new AuthenticationException('Access from this IP address is not allowed.');
        }

        $blockedCountries = array_map('strtoupper', (array) config('oneauth.security.blocked_countries', []));
        if ($country !== '' && $blockedCountries !== [] && in_array($country, $blockedCountries, true)) {
            throw new AuthenticationException('Access from this country is blocked.');
        }

        $allowedCountries = array_map('strtoupper', (array) config('oneauth.security.allowed_countries', []));
        if ($country !== '' && $allowedCountries !== [] && !in_array($country, $allowedCountries, true)) {
            throw new AuthenticationException('Access from this country is not allowed.');
        }
    }

    public function requestCountry(): string
    {
        $header = (string) (
            request()->header('CF-IPCountry')
            ?? request()->header('X-Country-Code')
            ?? request()->input('country')
            ?? config('oneauth.devices.default_country')
            ?? ''
        );

        return strtoupper(trim($header));
    }
}
