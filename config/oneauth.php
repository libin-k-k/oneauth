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
    ],

    'otp' => [
        'provider' => env('ONEAUTH_OTP_PROVIDER', 'email'),
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
        'providers' => ['google', 'apple'],
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
    ],

    'logging' => [
        'audit_enabled' => env('ONEAUTH_AUDIT_LOG_ENABLED', true),
    ],
];
