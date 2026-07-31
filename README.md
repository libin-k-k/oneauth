# OneAuth

[![Latest Stable Version](https://img.shields.io/packagist/v/libinkk/oneauth.svg)](https://packagist.org/packages/libinkk/oneauth)
[![Total Downloads](https://img.shields.io/packagist/dt/libinkk/oneauth.svg)](https://packagist.org/packages/libinkk/oneauth)
[![License](https://img.shields.io/packagist/l/libinkk/oneauth.svg)](#license)
[![PHP Version](https://img.shields.io/packagist/php-v/libinkk/oneauth.svg)](https://packagist.org/packages/libinkk/oneauth)

OneAuth is a modular, API-first authentication package for Laravel 9, 10, 11, 12, and 13.

It provides one public API for session authentication, Laravel Sanctum, JWT access and refresh tokens, email OTP and OTP login, anonymous login, email verification, password management with history and expiration, two-factor authentication (TOTP, email OTP, SMS OTP with a custom provider), social login via Socialite, session tracking, device tracking with trusted-device policies, account locks, IP and country access rules, suspicious-login events, and audit logs.

OneAuth does not install a frontend, modify your User model, or force one authentication driver. Your Laravel application controls its UI, guards, User model, mail transport, and external providers.

> [!IMPORTANT]
> OneAuth 1.x is under active development. Read [Current implementation status](#current-implementation-status) before using it in production. SMS and WhatsApp delivery remain extension stubs until you bind real providers.

## Contents

- [Why OneAuth](#why-oneauth)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Application setup](#application-setup)
- [Authentication drivers](#authentication-drivers)
- [Facade API tutorial](#facade-api-tutorial)
- [HTTP API tutorial](#http-api-tutorial)
- [OTP authentication](#otp-authentication)
- [Email verification](#email-verification)
- [Password management](#password-management)
- [Two-factor authentication](#two-factor-authentication)
- [Social authentication](#social-authentication)
- [Sessions and devices](#sessions-and-devices)
- [Middleware](#middleware)
- [Events](#events)
- [Configuration reference](#configuration-reference)
- [Database tables](#database-tables)
- [Extension points](#extension-points)
- [Artisan commands](#artisan-commands)
- [Testing](#testing)
- [Security checklist](#security-checklist)
- [Troubleshooting](#troubleshooting)
- [Current implementation status](#current-implementation-status)
- [Contributing](#contributing)
- [Support](#support)
- [License](#license)

## Why OneAuth

Laravel applications often combine multiple packages for session login, Sanctum, JWT, OTP, social login, verification, password reset, and 2FA. OneAuth organizes these concerns behind:

- A unified `OneAuth` facade
- Replaceable authentication drivers
- Replaceable OTP and OAuth providers
- Repository contracts for sessions and devices
- Configurable JSON API routes
- Package-owned migrations with the `oneauth_` prefix
- Laravel events for authentication outcomes
- Safe, repeatable installation commands

## Features

### Available in the package

- Email, username, or phone identifier lookup
- Registration with configurable password rules
- Session authentication driver
- Sanctum token driver
- JWT access tokens
- Opaque JWT refresh tokens with rotation
- Email OTP generation, delivery, hashing, expiration, cooldown, and attempt limits
- Numeric and alphanumeric OTP types
- Guest OTP send and OTP login
- Optional anonymous (guest) login
- Email verification tokens and temporary signed URLs
- Laravel password broker integration
- Password change, password history, and password expiration
- TOTP, email OTP, and SMS OTP two-factor methods (SMS delivery needs a custom provider)
- Single-use recovery codes and trusted-device 2FA skip
- Socialite providers: Google, Apple, GitHub, Facebook, Microsoft, LinkedIn, Twitter, Discord
- Active session records with revoke and logout-other-devices
- Device records with user-agent parsing, trust flags, country, and timezone
- Login attempt records, rate limiting, and persistent account locks
- IP and country allow/block lists
- Suspicious login detection for new devices
- Audit log writes for authentication events
- JSON API routes and exception envelope
- Facade-based API
- Middleware aliases
- Authentication events
- Install, publish, doctor, and cleanup commands
- GitHub Actions test matrix for Laravel 9 through 13

### Integration required

- Sanctum requires `laravel/sanctum` and `HasApiTokens`
- Social login requires Laravel Socialite and provider credentials
- SMS and WhatsApp OTP or notifications require custom provider implementations
- Session API routes require session middleware
- JWT and Sanctum request authentication require guard or middleware wiring in the host application
- Password reset requires Laravel's password broker, reset token storage, mail, and User provider configuration
- Country restrictions rely on `CF-IPCountry`, `X-Country-Code`, request input, or `ONEAUTH_DEFAULT_COUNTRY`

### Not included

- Blade, Vue, React, Livewire, Tailwind, or Bootstrap UI
- Automatic changes to the application User model
- Passport, LDAP, SAML, enterprise SSO, or OAuth server
- Magic links or passkeys
- Role and permission management

## Requirements

- Composer 2
- PHP `^8.0.2`
- Laravel 9, 10, 11, 12, or 13
- A configured database connection
- A Laravel authenticatable User model

The actual PHP requirement also depends on your Laravel major version. Composer resolves the compatible PHP and Illuminate dependency set.

## Installation

Install the package:

```bash
composer require libinkk/oneauth
```

Publish configuration and migrations:

```bash
php artisan oneauth:install
```

Run migrations:

```bash
php artisan migrate
```

You can install and migrate in one command:

```bash
php artisan oneauth:install --migrate
```

The install process is safe to run again:

- Existing `config/oneauth.php` is skipped
- Existing published OneAuth migration files are skipped
- Each package migration checks whether its table already exists
- Existing OneAuth tables are not recreated

Use `--force` only when you intentionally want to overwrite published files:

```bash
php artisan oneauth:install --force
php artisan oneauth:publish --force
```

Check your installation:

```bash
php artisan oneauth:doctor
```

The doctor command reports every OneAuth table as `[exists]` or `[missing]` and exits with a failure code when a table is missing.

## Application setup

### User model

OneAuth does not edit your User model. Your configured model must:

- Extend Laravel's authenticatable model
- Have a password attribute
- Have at least one configured identifier field
- Allow the registration attributes you want OneAuth to create
- Hide passwords and other secrets from JSON output

Example:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Libinkk\OneAuth\Traits\HasOneAuth;

class User extends Authenticatable
{
    use HasOneAuth;

    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
```

The `HasOneAuth` trait is optional. It provides:

```php
$user->oneauthSessions();
$user->oneauthDevices();
$user->oneauthSocialAccounts();
```

Use a different model through `.env`:

```env
ONEAUTH_USER_MODEL=App\Models\Customer
```

### Identifier fields

The default identifier fields are:

```php
'identifier_fields' => ['email', 'username', 'phone'],
```

Remove fields your users table does not contain. If your database has no `username` or `phone` column, leaving these defaults can produce SQL errors during login lookup.

### Mail

Email OTP, verification, and password reset need a configured Laravel mail transport:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Routes

OneAuth routes are enabled by default under `/oneauth`.

```env
ONEAUTH_ROUTES_ENABLED=true
ONEAUTH_ROUTE_PREFIX=oneauth
```

Disable package routes when using only the facade or your own controllers:

```env
ONEAUTH_ROUTES_ENABLED=false
```

## Authentication drivers

Choose one driver:

```env
ONEAUTH_DRIVER=session
```

Supported values are `session`, `sanctum`, and `jwt`.

### Session driver

The session driver uses Laravel's configured Auth guard:

```env
ONEAUTH_DRIVER=session
```

The default package route middleware is `api`, which usually does not start a session. For session-based HTTP authentication, publish the config and change:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'oneauth',
    'middleware' => ['web'],
],
```

For SPA setups, configure Laravel's session cookies, CSRF protection, stateful domains, and CORS for your application.

### Sanctum driver

Install Sanctum:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

Add `HasApiTokens` to your User model:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

Select the driver:

```env
ONEAUTH_DRIVER=sanctum
```

Login returns a Sanctum plain-text token:

```json
{
  "user": {},
  "token": "1|plain-text-token",
  "refresh_token": null
}
```

Your application must configure Sanctum middleware or guards so Bearer tokens populate Laravel's current Auth user on protected requests.

### JWT driver

`firebase/php-jwt` is installed with OneAuth. Configure a dedicated secret:

```env
ONEAUTH_DRIVER=jwt
ONEAUTH_JWT_SECRET=replace-with-a-long-random-secret
ONEAUTH_JWT_TTL=60
ONEAUTH_JWT_REFRESH_TTL=10080
```

Generate a secret:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

JWT login returns:

```json
{
  "user": {},
  "token": "access-jwt",
  "refresh_token": "opaque-refresh-token"
}
```

Refresh the token pair:

```bash
curl -X POST http://localhost/oneauth/refresh \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"opaque-refresh-token"}'
```

Refresh tokens are stored as SHA-256 hashes and rotated after use.

> [!NOTE]
> OneAuth issues and rotates JWTs, authenticates Bearer access tokens in `oneauth.auth`, and revokes refresh tokens plus recently issued access tokens on logout and credential revoke.

## Facade API tutorial

Import the facade:

```php
use Libinkk\OneAuth\Facades\OneAuth;
```

### Register

```php
$result = OneAuth::register([
    'name' => 'Taylor Doe',
    'email' => 'taylor@example.com',
    'password' => 'Password123',
]);

$user = $result['user'];
```

Registration does not automatically log in the user.

The default password policy requires:

- At least 8 characters
- One uppercase letter
- One lowercase letter
- One number

### Login

Login with email:

```php
$result = OneAuth::login([
    'email' => 'taylor@example.com',
    'password' => 'Password123',
]);
```

Login with username:

```php
$result = OneAuth::login([
    'username' => 'taylor',
    'password' => 'Password123',
]);
```

Login with phone:

```php
$result = OneAuth::login([
    'phone' => '+15551234567',
    'password' => 'Password123',
]);
```

You can also use the generic identifier key:

```php
$result = OneAuth::login([
    'identifier' => 'taylor@example.com',
    'password' => 'Password123',
]);
```

The result shape is driver-dependent:

```php
[
    'user' => $user,
    'token' => $accessTokenOrNull,
    'refresh_token' => $refreshTokenOrNull,
]
```

### Current user

```php
$user = OneAuth::user();
```

### Logout

```php
OneAuth::logout();
```

### Refresh

```php
$result = OneAuth::refresh();
```

For JWT, the current request must contain `refresh_token`.

### Select a driver at runtime

```php
$jwt = OneAuth::driver('jwt');
$result = $jwt->login([
    'email' => 'taylor@example.com',
    'password' => 'Password123',
]);
```

Driver methods:

- `login(array $credentials)`
- `logout()`
- `refresh()`
- `user()`
- `check()`
- `guest()`
- `token()`

## HTTP API tutorial

All package endpoints return JSON. The default base path is:

```text
/oneauth
```

Send these headers:

```text
Accept: application/json
Content-Type: application/json
```

### Public endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/oneauth/register` | Register a user |
| `POST` | `/oneauth/login` | Authenticate credentials |
| `POST` | `/oneauth/refresh` | Refresh driver credentials (JWT refresh token) |
| `POST` | `/oneauth/social/{provider}/login` | Authenticate with Google or Apple token |
| `POST` | `/oneauth/2fa/challenge` | Complete login after a 2FA challenge token |
| `GET` | `/oneauth/email/verify/signed` | Process a temporary signed verification URL |
| `POST` | `/oneauth/password/forgot` | Send a Laravel password reset link |
| `POST` | `/oneauth/password/reset` | Reset a password with broker token |

### Protected endpoints

These routes use `oneauth.auth`:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/oneauth/logout` | Log out |
| `GET` | `/oneauth/user` | Return current user |
| `POST` | `/oneauth/email/send-verification` | Send verification token and link |
| `POST` | `/oneauth/email/verify` | Verify email token |
| `POST` | `/oneauth/otp/send` | Send an OTP |
| `POST` | `/oneauth/otp/verify` | Verify an OTP |
| `POST` | `/oneauth/2fa/enable` | Enable 2FA (returns secret, recovery codes, `otpauth_uri`) |
| `POST` | `/oneauth/2fa/verify` | Verify TOTP or recovery code for an authenticated session |
| `POST` | `/oneauth/2fa/disable` | Disable 2FA (requires password or 2FA code) |
| `GET` | `/oneauth/sessions` | List tracked sessions |
| `DELETE` | `/oneauth/sessions/{sessionId}` | Revoke one tracked session |
| `POST` | `/oneauth/sessions/logout-others` | Revoke all sessions except the current one |
| `GET` | `/oneauth/devices` | List tracked devices |
| `POST` | `/oneauth/devices/{fingerprint}/trust` | Mark a device trusted or untrusted |
| `POST` | `/oneauth/password/change` | Change current password |

### Register request

```bash
curl -X POST http://localhost/oneauth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Taylor Doe",
    "email": "taylor@example.com",
    "password": "Password123"
  }'
```

### Login request

```bash
curl -X POST http://localhost/oneauth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "taylor@example.com",
    "password": "Password123"
  }'
```

### Current user request

```bash
curl http://localhost/oneauth/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

The Authorization header works only after your selected guard or custom middleware authenticates the token into Laravel Auth.

## OTP authentication

The default provider sends OTP codes through Laravel Mail.

```env
ONEAUTH_OTP_PROVIDER=email
ONEAUTH_OTP_TYPE=numeric
ONEAUTH_OTP_LENGTH=6
ONEAUTH_OTP_EXPIRES_IN=300
ONEAUTH_OTP_MAX_ATTEMPTS=5
ONEAUTH_OTP_COOLDOWN=30
```

Send an OTP with the facade. Authenticated users can omit the identifier. Guests can send a login OTP by email, username, or phone:

```php
$challenge = OneAuth::sendOtp([
    'email' => 'taylor@example.com',
    'purpose' => 'login',
    'channel' => 'email',
    'target' => 'taylor@example.com',
]);
```

Verify an OTP:

```php
$verified = OneAuth::verifyOtp([
    'email' => 'taylor@example.com',
    'purpose' => 'login',
    'target' => 'taylor@example.com',
    'code' => '123456',
]);
```

Complete an OTP login (verifies the code and establishes the configured driver session or tokens):

```php
$result = OneAuth::loginWithOtp([
    'email' => 'taylor@example.com',
    'target' => 'taylor@example.com',
    'code' => '123456',
]);
```

HTTP requests:

```bash
curl -X POST http://localhost/oneauth/otp/send \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"taylor@example.com","purpose":"login","channel":"email","target":"taylor@example.com"}'

curl -X POST http://localhost/oneauth/login/otp \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"taylor@example.com","target":"taylor@example.com","code":"123456"}'
```

OTP security behavior:

- Codes use `random_int` for numeric OTPs or secure alphanumeric generation
- Codes are stored with Laravel Hash
- Expiration is enforced
- Attempt limits are enforced
- Send cooldown is enforced
- A verified code cannot be reused

### Custom SMS or WhatsApp provider

The built-in SMS and WhatsApp provider classes are stubs. Bind your own implementation of `OTPProviderInterface`:

```php
<?php

namespace App\Auth;

use Libinkk\OneAuth\Contracts\OTPProviderInterface;

class TwilioOtpProvider implements OTPProviderInterface
{
    public function send(
        string $channel,
        string $to,
        string $code,
        array $context = []
    ): void {
        // Send the code through your provider.
    }
}
```

Register it in an application service provider:

```php
use App\Auth\TwilioOtpProvider;
use Libinkk\OneAuth\Contracts\OTPProviderInterface;

public function register(): void
{
    $this->app->bind(OTPProviderInterface::class, TwilioOtpProvider::class);
}
```

The configured `ONEAUTH_OTP_PROVIDER` selects the bound package provider. The `channel` request field does not dynamically replace the container binding.

## Email verification

Send a verification message:

```php
$result = OneAuth::sendEmailVerification([
    'email' => 'taylor@example.com',
]);
```

The response contains:

```php
[
    'id' => 1,
    'expires_at' => $date,
    'signed_url' => $url,
]
```

Verify a token:

```php
$verified = OneAuth::verifyEmail([
    'email' => 'taylor@example.com',
    'token' => 'token-from-email',
]);
```

The package:

- Stores only a hash of the verification token
- Uses a 30-minute expiration
- Sends a raw email containing the token and temporary signed URL
- Updates `email_verified_at` when that attribute exists
- Dispatches `EmailVerified`

Require email verification during login:

```env
ONEAUTH_REQUIRE_VERIFIED_EMAIL=true
```

> [!NOTE]
> Guest verification with a token is supported through `OneAuth::verifyEmail` and the signed URL route. Authenticated users can also request a fresh verification email.

## Password management

### Forgot password

```bash
curl -X POST http://localhost/oneauth/password/forgot \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"taylor@example.com"}'
```

This delegates to Laravel's configured password broker.

### Reset password

```bash
curl -X POST http://localhost/oneauth/password/reset \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "taylor@example.com",
    "token": "reset-token",
    "password": "NewPassword123",
    "password_confirmation": "NewPassword123"
  }'
```

### Change password

```bash
curl -X POST http://localhost/oneauth/password/change \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "Password123",
    "new_password": "NewPassword123"
  }'
```

Password resets and changes record the resulting hash in `oneauth_password_history`, reject reuse against the configured `history_limit`, prune older history rows, and revoke OneAuth sessions plus Sanctum/JWT credentials.

Facade helpers:

```php
OneAuth::forgotPassword(['email' => 'user@example.com']);
OneAuth::resetPassword([
    'email' => 'user@example.com',
    'token' => $brokerToken,
    'password' => 'NewPassword123',
]);
OneAuth::changePassword([
    'current_password' => 'Password123',
    'new_password' => 'NewPassword123',
]);
```

Set `password_policy.require_symbol` to `true` when symbol characters are required.

Optional password expiration (days since last recorded password change; `0` disables):

```env
ONEAUTH_PASSWORD_EXPIRES_DAYS=90
```

Expired passwords block login until the user resets or changes their password.

## Two-factor authentication

Enable TOTP 2FA (stores a pending secret until confirmed):

```php
$setup = OneAuth::enableTwoFactor([
    'method' => 'totp',
]);
```

Enable email OTP 2FA (sends a confirmation OTP; SMS needs a custom OTP provider):

```php
$setup = OneAuth::enableTwoFactor([
    'method' => 'email',
]);
```

The TOTP setup result returns the secret and plaintext recovery codes once:

```php
[
    'method' => 'totp',
    'secret' => 'BASE32SECRET',
    'recovery_codes' => [
        'RECOVERY01',
        'RECOVERY02',
    ],
    'confirmed' => false,
    'otpauth_uri' => 'otpauth://totp/OneAuth:user@example.com?secret=...',
    'issuer' => 'OneAuth',
]
```

Render a QR code from `otpauth_uri` in your application UI. Secrets are RFC 4648 base32 for authenticator-app compatibility.

Confirm setup with a package TOTP or email/SMS OTP code (this sets `enabled` and fires `TwoFactorEnabled`):

```php
$verified = OneAuth::verifyTwoFactor([
    'code' => '123456',
]);
```

Verify and consume a recovery code:

```php
$verified = OneAuth::verifyTwoFactor([
    'recovery_code' => 'RECOVERY01',
]);
```

Disable 2FA (requires current password or a valid TOTP/recovery code):

```php
OneAuth::disableTwoFactor([
    'password' => $currentPassword,
]);
```

The encrypted secret and recovery codes are cleared when disabled.

When login requires 2FA, OneAuth throws `TwoFactorRequiredException` with a short-lived `challenge_token`. Complete the login:

```php
try {
    $result = OneAuth::login($credentials);
} catch (\Libinkk\OneAuth\Exceptions\TwoFactorRequiredException $e) {
    $result = OneAuth::completeTwoFactorLogin([
        'challenge_token' => $e->getChallengeToken(),
        'code' => $totpCode,
    ]);
}
```

HTTP login returns `403` with `two_factor_required` and `challenge_token`. Finish with `POST /oneauth/2fa/challenge`.

Skip 2FA on trusted devices when enabled:

```env
ONEAUTH_2FA_SKIP_TRUSTED_DEVICE=true
```

Mark a device trusted after login with `OneAuth::trustDevice($fingerprint)` or `POST /oneauth/devices/{fingerprint}/trust`.

> [!WARNING]
> Pending 2FA secrets created before v1.2 used a package-specific secret format. Re-enable 2FA after upgrading if an older pending setup cannot be confirmed in an authenticator app.

## Social authentication

Install Socialite:

```bash
composer require laravel/socialite
```

Configure credentials in `config/services.php`.

Google example:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
```

Environment:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

Exchange a provider access token:

```php
$result = OneAuth::socialLogin('google', [
    'token' => $providerAccessToken,
]);
```

HTTP:

```bash
curl -X POST http://localhost/oneauth/social/google/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"token":"provider-access-token"}'
```

Supported provider names (configure Socialite + `config/services.php` for each):

- `google`
- `apple`
- `github`
- `facebook`
- `microsoft`
- `linkedin`
- `twitter`
- `discord`

Providers are resolved from `oneauth.social.providers` through a shared Socialite adapter.

The social flow:

1. Resolves the remote user through Socialite `userFromToken`
2. Finds an existing `oneauth_social_accounts` link
3. Optionally links a verified email match when `social.link_by_email` is true (default `false`)
4. Creates a user when `social.create_user_if_missing` is true and no conflicting email exists
5. Links the provider account
6. Dispatches `SocialLogin`
7. Runs the shared login pipeline (verified-email gate, 2FA challenge, then `establish` for the active driver)

Apple may require a compatible Socialite provider adapter and Laravel event registration because Apple is not included in every Socialite installation.

## Sessions and devices

Every successful login records:

- One row in `oneauth_sessions`
- One device row matched by a SHA-1 fingerprint of user agent and IP when no fingerprint is supplied
- One successful login attempt

List sessions:

```php
$sessions = OneAuth::sessions();
```

List devices:

```php
$devices = OneAuth::devices();
```

HTTP:

```text
GET /oneauth/sessions
GET /oneauth/devices
```

Session records use `ONEAUTH_IDLE_TIMEOUT` for their expiry timestamp.

Device fingerprints and IP data are signals only. Do not treat them as proof of identity.

Device rows store parsed browser, OS, and device name when `oneauth.devices.parse_user_agent` is true. Trust a device:

```php
OneAuth::trustDevice($fingerprint, true);
```

```bash
curl -X POST http://localhost/oneauth/devices/{fingerprint}/trust \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"trusted":true}'
```

Revoke a session or other devices:

```php
OneAuth::revokeSession($sessionId);
OneAuth::logoutOtherSessions();
```

### Anonymous login

Opt-in guest accounts (disabled by default):

```env
ONEAUTH_ANONYMOUS_LOGIN=true
```

```php
$result = OneAuth::anonymousLogin();
```

```bash
curl -X POST http://localhost/oneauth/login/anonymous \
  -H "Accept: application/json"
```

### Security controls

```env
ONEAUTH_MAX_LOGIN_ATTEMPTS=5
ONEAUTH_LOCKOUT_SECONDS=300
ONEAUTH_DETECT_SUSPICIOUS_LOGIN=true
ONEAUTH_ALLOWED_IPS=
ONEAUTH_BLOCKED_IPS=
ONEAUTH_ALLOWED_COUNTRIES=
ONEAUTH_BLOCKED_COUNTRIES=
ONEAUTH_DEFAULT_COUNTRY=
```

After too many failed attempts OneAuth creates a row in `oneauth_account_locks`. Use `OneAuth::lockAccount()` / `OneAuth::unlockAccount()` for manual control. New-device logins dispatch `SuspiciousLoginDetected` when detection is enabled.

## Middleware

OneAuth registers:

| Alias | Behavior |
| --- | --- |
| `oneauth.auth` | Requires the selected driver to report an authenticated Laravel user |
| `oneauth.verified` | Requires an authenticated user with a populated `email_verified_at` |
| `oneauth.otp` | Requires `oneauth.otp_verified` in the session |
| `oneauth.twofactor` | Requires `oneauth.twofactor_verified` in the session |

Apply middleware to application routes:

```php
Route::middleware(['oneauth.auth', 'oneauth.verified'])->group(function () {
    Route::get('/account', AccountController::class);
});
```

OTP and two-factor middleware currently use session flags. They are not token-scoped for pure JWT APIs.

## Events

Listen to OneAuth events in your application:

```php
use Libinkk\OneAuth\Events\UserLoggedIn;

class RecordLoginAnalytics
{
    public function handle(UserLoggedIn $event): void
    {
        $user = $event->user;
    }
}
```

Dispatched events:

- `UserRegistered`
- `UserLoggedIn`
- `UserLoggedOut`
- `OTPSent`
- `OTPVerified`
- `EmailVerified`
- `PasswordReset`
- `PasswordChanged`
- `TwoFactorEnabled`
- `TwoFactorDisabled`
- `SocialLogin`
- `SuspiciousLoginDetected`
- `AccountLocked`

All event objects expose:

```php
$event->user;
$event->context;
```

Do not place tokens, OTP codes, passwords, recovery codes, or provider secrets in event context.

## Configuration reference

Publish configuration:

```bash
php artisan oneauth:publish
```

Available environment variables:

```env
# Driver and user
ONEAUTH_DRIVER=session
ONEAUTH_USER_MODEL=App\Models\User

# Routes
ONEAUTH_ROUTES_ENABLED=true
ONEAUTH_ROUTE_PREFIX=oneauth

# Security
ONEAUTH_REQUIRE_VERIFIED_EMAIL=false
ONEAUTH_MAX_LOGIN_ATTEMPTS=5
ONEAUTH_LOCKOUT_SECONDS=300
ONEAUTH_PASSWORD_EXPIRES_DAYS=0
ONEAUTH_DETECT_SUSPICIOUS_LOGIN=true
ONEAUTH_ALLOWED_IPS=
ONEAUTH_BLOCKED_IPS=
ONEAUTH_ALLOWED_COUNTRIES=
ONEAUTH_BLOCKED_COUNTRIES=

# OTP
ONEAUTH_OTP_PROVIDER=email
ONEAUTH_OTP_TYPE=numeric
ONEAUTH_OTP_LENGTH=6
ONEAUTH_OTP_EXPIRES_IN=300
ONEAUTH_OTP_MAX_ATTEMPTS=5
ONEAUTH_OTP_COOLDOWN=30
ONEAUTH_OTP_RESEND_LIMIT=3

# Sessions
ONEAUTH_IDLE_TIMEOUT=7200

# JWT
ONEAUTH_JWT_SECRET=
ONEAUTH_JWT_TTL=60
ONEAUTH_JWT_REFRESH_TTL=10080

# Two factor
ONEAUTH_2FA_ENABLED=true
ONEAUTH_TOTP_ISSUER="${APP_NAME}"
ONEAUTH_2FA_SKIP_TRUSTED_DEVICE=false

# Anonymous
ONEAUTH_ANONYMOUS_LOGIN=false

# Devices
ONEAUTH_DEFAULT_COUNTRY=
ONEAUTH_DEFAULT_TIMEZONE=

# Logging
ONEAUTH_AUDIT_LOG_ENABLED=true
```

Values in seconds:

- `ONEAUTH_LOCKOUT_SECONDS`
- `ONEAUTH_OTP_EXPIRES_IN`
- `ONEAUTH_OTP_COOLDOWN`
- `ONEAUTH_IDLE_TIMEOUT`

Values in minutes:

- `ONEAUTH_JWT_TTL`
- `ONEAUTH_JWT_REFRESH_TTL`

The full PHP configuration is in `config/oneauth.php`.

## Database tables

OneAuth uses morph columns instead of a foreign key to a specific users table.

| Table | Purpose |
| --- | --- |
| `oneauth_otps` | Hashed OTP challenges, attempts, and expiration |
| `oneauth_devices` | Device metadata and fingerprint |
| `oneauth_sessions` | Tracked application sessions |
| `oneauth_social_accounts` | OAuth provider account links |
| `oneauth_email_verifications` | Hashed email verification tokens |
| `oneauth_two_factor` | Encrypted 2FA secret and hashed recovery codes |
| `oneauth_login_attempts` | Successful and failed login attempts |
| `oneauth_password_history` | Historical password hashes |
| `oneauth_refresh_tokens` | Hashed JWT refresh tokens |
| `oneauth_audit_logs` | Authentication and security audit events |
| `oneauth_account_locks` | Persistent identifier locks after brute-force or manual lock |

Migrations are safe to run more than once because each migration checks `Schema::hasTable()` before creating its table.

## Extension points

OneAuth provides these contracts:

```php
Libinkk\OneAuth\Contracts\AuthenticationDriverInterface
Libinkk\OneAuth\Contracts\OTPProviderInterface
Libinkk\OneAuth\Contracts\OAuthProviderInterface
Libinkk\OneAuth\Contracts\NotificationProviderInterface
Libinkk\OneAuth\Contracts\SessionRepositoryInterface
Libinkk\OneAuth\Contracts\DeviceRepositoryInterface
```

Override a binding in your application service provider:

```php
use App\Auth\DatabaseSessionRepository;
use Libinkk\OneAuth\Contracts\SessionRepositoryInterface;

public function register(): void
{
    $this->app->bind(
        SessionRepositoryInterface::class,
        DatabaseSessionRepository::class
    );
}
```

### Custom authentication driver

Implement:

```php
interface AuthenticationDriverInterface
{
    public function login(array $credentials): array;
    public function logout(): void;
    public function refresh(): array;
    public function user(): mixed;
    public function check(): bool;
    public function guest(): bool;
    public function token(): ?string;
}
```

The current manager resolves only the built-in names `session`, `sanctum`, and `jwt`. To add a named driver, extend or replace `OneAuthManager` in the container.

`NotificationProviderInterface` defaults to the email implementation. SMS remains a stub until you bind a real provider.

## Artisan commands

### Install

```bash
php artisan oneauth:install
php artisan oneauth:install --migrate
php artisan oneauth:install --force
```

### Publish

```bash
php artisan oneauth:publish
php artisan oneauth:publish --force
```

### Diagnose

```bash
php artisan oneauth:doctor
```

### Cleanup

```bash
php artisan oneauth:cleanup
```

Cleanup deletes:

- Expired OTP challenges
- Expired refresh tokens
- Revoked refresh tokens
- Expired tracked sessions

Schedule cleanup in `routes/console.php` or your console kernel:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('oneauth:cleanup')->hourly();
```

For older Laravel applications:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('oneauth:cleanup')->hourly();
}
```

## Testing

Install development dependencies:

```bash
composer install
```

Run tests:

```bash
vendor/bin/phpunit
```

On Windows:

```powershell
vendor\bin\phpunit
```

The repository currently includes:

- A Testbench session registration and login feature test
- A TOTP generation and verification unit test
- An in-memory SQLite test database

When contributing, add focused tests for every changed security flow and run the relevant Laravel compatibility matrix.

## Security checklist

Before production use:

- Set a dedicated, strong `ONEAUTH_JWT_SECRET`
- Use HTTPS
- Configure trusted proxies correctly
- Configure Laravel mail and queues
- Hide `password` and `remember_token` in the User model
- Restrict mass assignment on the User model
- Remove identifier fields that do not exist in your users table
- Configure session cookies, CSRF, CORS, and stateful domains
- Use `oneauth.auth` for JWT Bearer protection; wire Sanctum middleware when using the Sanctum driver
- Keep `social.link_by_email` disabled unless you accept verified-email auto-linking
- Bind production SMS or WhatsApp providers before selecting them
- Add application-level request validation and exception mapping
- Schedule `oneauth:cleanup`
- Back up data before package upgrades

Never log:

- Passwords
- Raw OTP codes
- Access tokens
- Refresh tokens
- Recovery codes
- OAuth provider secrets
- TOTP secrets

## Troubleshooting

### Table already exists

OneAuth migrations skip tables that already exist. Run:

```bash
php artisan oneauth:doctor
php artisan migrate
```

If a table exists with an incompatible schema, back it up and reconcile the schema manually. A table-name match does not validate its columns.

### Table is missing

```bash
php artisan oneauth:publish
php artisan migrate
php artisan oneauth:doctor
```

### Session login does not persist

The default route middleware is `api`. Publish the config and change route middleware to `web`, or add your application session middleware.

### Unauthenticated with a valid JWT

`oneauth.auth` accepts a Bearer JWT access token when the JWT driver (or a valid OneAuth JWT) is present. Confirm `ONEAUTH_JWT_SECRET` matches the issuing app and that the token has not expired.

### Sanctum is not installed

```bash
composer require laravel/sanctum
```

Add `HasApiTokens` to the User model and complete Sanctum's installation.

### Socialite is not installed

```bash
composer require laravel/socialite
```

Then configure `config/services.php`.

### SMS or WhatsApp OTP throws an exception

Those providers are extension stubs. Bind your own `OTPProviderInterface` implementation, switch `oneauth.otp.provider` to `email`, or run `php artisan oneauth:doctor` to confirm the configuration.

## Current implementation status

The following details are important for honest production evaluation:

- Email OTP and OTP login are implemented; SMS and WhatsApp OTP remain extension stubs (doctor fails if stubs are selected)
- Session, Sanctum, and JWT drivers issue credentials with Bearer auth, refresh, and revoke flows
- TOTP uses base32 secrets with `otpauth_uri`; email 2FA uses package OTP; SMS 2FA needs a custom OTP provider
- Trusted devices can skip 2FA when `ONEAUTH_2FA_SKIP_TRUSTED_DEVICE` is true
- Password expiration, account locks, IP/country allow and block lists, and suspicious-login events are enforced from config
- Device rows parse browser, OS, and device name from the user agent
- Anonymous login is opt-in via `ONEAUTH_ANONYMOUS_LOGIN`
- Audit logs are written automatically when `ONEAUTH_AUDIT_LOG_ENABLED` is true
- OneAuth exceptions return a JSON envelope (`message`, `error`, context) on package/API routes
- Social login supports Google, Apple, GitHub, Facebook, Microsoft, LinkedIn, Twitter, and Discord via Socialite
- Session revoke and logout-other-devices APIs are available on the facade and HTTP routes
- GitHub Actions runs a Laravel 9–13 PHPUnit matrix on push and pull requests

These notes are documented so developers can make an informed decision and contribute improvements.

## Contributing

Contributions are welcome.

1. Fork the repository
2. Create a feature branch
3. Keep changes modular and driver-independent
4. Add or update tests
5. Run `vendor/bin/phpunit`
6. Update documentation
7. Open a pull request with a clear description and test plan

Contribution rules:

- Support Laravel 9, 10, 11, 12, and 13
- Do not add frontend dependencies
- Do not modify the application User model automatically
- Keep secrets out of logs, events, and exceptions
- Use contracts across package module boundaries
- Preserve backward compatibility or include upgrade notes

Repository:

`https://github.com/libin-k-k/oneauth`

Issues:

`https://github.com/libin-k-k/oneauth/issues`

## Support

- Website: [https://www.libinkk.in](https://www.libinkk.in)
- Email: [contact@libinkk.in](mailto:contact@libinkk.in)
- Author: Libin K K
- Author email: [libinkk1999@gmail.com](mailto:libinkk1999@gmail.com)

Please use GitHub Issues for reproducible bugs and feature requests. Do not post credentials, tokens, OTP codes, or private application data.

## License

OneAuth is open-source software licensed under the MIT License.
