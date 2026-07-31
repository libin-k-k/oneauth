<?php

return [
    'driver' => env('ONEAUTH_DRIVER', 'session'),

    'user_model' => env('ONEAUTH_USER_MODEL', \App\Models\User::class),

    'identifier_fields' => ['email', 'username', 'phone'],

    'routes' => [
        'enabled' => env('ONEAUTH_ROUTES_ENABLED', true),
        'prefix' => env('ONEAUTH_ROUTE_PREFIX', 'oneauth'),
        'middleware' => ['api'],
    ],

    'security' => [
        'require_verified_email' => env('ONEAUTH_REQUIRE_VERIFIED_EMAIL', false),
        'max_login_attempts' => env('ONEAUTH_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_seconds' => env('ONEAUTH_LOCKOUT_SECONDS', 300),
        'password_expires_days' => env('ONEAUTH_PASSWORD_EXPIRES_DAYS', 0),
        'detect_suspicious_login' => env('ONEAUTH_DETECT_SUSPICIOUS_LOGIN', true),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONEAUTH_ALLOWED_IPS', ''))))),
        'blocked_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONEAUTH_BLOCKED_IPS', ''))))),
        'allowed_countries' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONEAUTH_ALLOWED_COUNTRIES', ''))))),
        'blocked_countries' => array_values(array_filter(array_map('trim', explode(',', (string) env('ONEAUTH_BLOCKED_COUNTRIES', ''))))),
    ],

    'otp' => [
        'provider' => env('ONEAUTH_OTP_PROVIDER', 'email'),
        'type' => env('ONEAUTH_OTP_TYPE', 'numeric'),
        'length' => env('ONEAUTH_OTP_LENGTH', 6),
        'expires_in_seconds' => env('ONEAUTH_OTP_EXPIRES_IN', 300),
        'max_attempts' => env('ONEAUTH_OTP_MAX_ATTEMPTS', 5),
        'cooldown_seconds' => env('ONEAUTH_OTP_COOLDOWN', 30),
        'resend_limit' => env('ONEAUTH_OTP_RESEND_LIMIT', 3),
    ],

    'session' => [
        'idle_timeout_seconds' => env('ONEAUTH_IDLE_TIMEOUT', 7200),
    ],

    'jwt' => [
        'issuer' => env('APP_NAME', 'oneauth'),
        'secret' => env('ONEAUTH_JWT_SECRET', env('APP_KEY')),
        'ttl_minutes' => env('ONEAUTH_JWT_TTL', 60),
        'refresh_ttl_minutes' => env('ONEAUTH_JWT_REFRESH_TTL', 10080),
    ],

    'social' => [
        'providers' => ['google', 'apple', 'github', 'facebook', 'microsoft', 'linkedin', 'twitter', 'discord'],
        'create_user_if_missing' => true,
        'link_by_email' => env('ONEAUTH_SOCIAL_LINK_BY_EMAIL', false),
    ],

    'password_policy' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_symbol' => false,
        'history_limit' => 5,
    ],

    'two_factor' => [
        'enabled' => env('ONEAUTH_2FA_ENABLED', true),
        'totp_issuer' => env('ONEAUTH_TOTP_ISSUER', env('APP_NAME', 'OneAuth')),
        'recovery_codes_count' => 8,
        'challenge_ttl_seconds' => env('ONEAUTH_2FA_CHALLENGE_TTL', 300),
        'skip_on_trusted_device' => env('ONEAUTH_2FA_SKIP_TRUSTED_DEVICE', false),
    ],

    'anonymous' => [
        'enabled' => env('ONEAUTH_ANONYMOUS_LOGIN', false),
        'name_prefix' => 'Guest',
    ],

    'devices' => [
        'parse_user_agent' => true,
        'default_country' => env('ONEAUTH_DEFAULT_COUNTRY'),
        'default_timezone' => env('ONEAUTH_DEFAULT_TIMEZONE'),
    ],

    'logging' => [
        'audit_enabled' => env('ONEAUTH_AUDIT_LOG_ENABLED', true),
    ],
];
