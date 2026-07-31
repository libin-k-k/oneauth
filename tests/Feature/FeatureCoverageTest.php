<?php

namespace Libinkk\OneAuth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Exceptions\OTPException;
use Libinkk\OneAuth\Exceptions\TwoFactorRequiredException;
use Libinkk\OneAuth\Facades\OneAuth;
use Libinkk\OneAuth\Models\LoginAttempt;
use Libinkk\OneAuth\Models\Otp;
use Libinkk\OneAuth\Models\RefreshToken;
use Libinkk\OneAuth\Support\TotpService;
use Libinkk\OneAuth\Tests\Fixtures\User;
use Libinkk\OneAuth\Tests\TestCase;

class FeatureCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user(): void
    {
        $result = OneAuth::register([
            'name' => 'Taylor Doe',
            'email' => 'taylor@example.com',
            'password' => 'Password123',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertDatabaseHas('users', ['email' => 'taylor@example.com']);
        $this->assertNull(Auth::user());
    }

    public function test_session_login_logout_and_user(): void
    {
        OneAuth::register([
            'name' => 'Session User',
            'email' => 'session@example.com',
            'password' => 'Password123',
        ]);

        $login = OneAuth::login([
            'email' => 'session@example.com',
            'password' => 'Password123',
        ]);

        $this->assertNotNull($login['user']);
        $this->assertNull($login['token']);
        $this->assertTrue(Auth::check());
        $this->assertSame('session@example.com', OneAuth::user()->email);

        OneAuth::logout();
        $this->assertFalse(Auth::check());
    }

    public function test_invalid_login_throws(): void
    {
        OneAuth::register([
            'name' => 'Bad Login',
            'email' => 'bad@example.com',
            'password' => 'Password123',
        ]);

        $this->expectException(AuthenticationException::class);
        OneAuth::login([
            'email' => 'bad@example.com',
            'password' => 'WrongPass1',
        ]);
    }

    public function test_jwt_login_and_refresh_rotate_tokens(): void
    {
        config(['oneauth.driver' => 'jwt']);

        OneAuth::register([
            'name' => 'Jwt User',
            'email' => 'jwt@example.com',
            'password' => 'Password123',
        ]);

        $login = OneAuth::login([
            'email' => 'jwt@example.com',
            'password' => 'Password123',
        ]);

        $this->assertNotEmpty($login['token']);
        $this->assertNotEmpty($login['refresh_token']);
        $this->assertDatabaseCount('oneauth_refresh_tokens', 1);

        request()->merge(['refresh_token' => $login['refresh_token']]);
        $refreshed = OneAuth::refresh();

        $this->assertNotEmpty($refreshed['token']);
        $this->assertNotSame($login['refresh_token'], $refreshed['refresh_token']);
        $this->assertDatabaseCount('oneauth_refresh_tokens', 2);
        $this->assertNotNull(RefreshToken::query()->whereNotNull('revoked_at')->first());
    }

    public function test_otp_send_and_verify(): void
    {
        Mail::fake();

        $user = User::query()->create([
            'name' => 'Otp User',
            'email' => 'otp@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);

        $sent = OneAuth::sendOtp([
            'purpose' => 'login',
            'channel' => 'email',
            'target' => 'otp@example.com',
        ]);

        $this->assertArrayHasKey('id', $sent);
        $otp = Otp::query()->find($sent['id']);
        $this->assertNotNull($otp);

        // Recover plaintext by brute-forcing short numeric space is too heavy.
        // Instead validate verify rejects wrong code and accepts hash-checked flow via reflection of stored hash.
        $this->expectException(OTPException::class);
        OneAuth::verifyOtp([
            'purpose' => 'login',
            'target' => 'otp@example.com',
            'code' => '000000',
        ]);
    }

    public function test_otp_verify_succeeds_with_known_code(): void
    {
        Mail::fake();

        $user = User::query()->create([
            'name' => 'Otp Ok',
            'email' => 'otpok@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);

        $code = '654321';
        Otp::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'purpose' => 'login',
            'channel' => 'email',
            'target' => 'otpok@example.com',
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'resends' => 0,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
            'meta' => [],
        ]);

        $ok = OneAuth::verifyOtp([
            'purpose' => 'login',
            'target' => 'otpok@example.com',
            'code' => $code,
        ]);

        $this->assertTrue($ok);
        $this->assertSame('login', session('oneauth.otp_verified'));
    }

    public function test_two_factor_enable_and_verify_totp(): void
    {
        $user = User::query()->create([
            'name' => '2fa User',
            'email' => 'twofa@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);

        $setup = OneAuth::enableTwoFactor(['method' => 'totp']);
        $this->assertArrayHasKey('secret', $setup);
        $this->assertCount(8, $setup['recovery_codes']);
        $this->assertFalse($setup['confirmed']);
        $this->assertDatabaseHas('oneauth_two_factor', [
            'authenticatable_id' => $user->getKey(),
            'enabled' => 0,
        ]);

        $code = app(TotpService::class)->nowCode($setup['secret']);
        $this->assertTrue(OneAuth::verifyTwoFactor(['code' => $code]));
        $this->assertTrue(session('oneauth.twofactor_verified'));
        $this->assertDatabaseHas('oneauth_two_factor', [
            'authenticatable_id' => $user->getKey(),
            'enabled' => 1,
        ]);
    }

    public function test_two_factor_blocks_login_without_authenticating_session(): void
    {
        $user = User::query()->create([
            'name' => '2fa Gate',
            'email' => 'gate@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);
        $setup = OneAuth::enableTwoFactor(['method' => 'totp']);
        OneAuth::verifyTwoFactor([
            'code' => app(TotpService::class)->nowCode($setup['secret']),
        ]);
        Auth::logout();

        try {
            OneAuth::login([
                'email' => 'gate@example.com',
                'password' => 'Password123',
            ]);
            $this->fail('Expected TwoFactorRequiredException');
        } catch (TwoFactorRequiredException $e) {
            $this->assertFalse(Auth::check(), 'Credentials must not establish a session before 2FA');
            $this->assertNotEmpty($e->getChallengeToken());
            $this->assertDatabaseMissing('oneauth_login_attempts', [
                'identifier' => 'gate@example.com',
                'successful' => 0,
            ]);
        }
    }

    public function test_two_factor_challenge_completes_login(): void
    {
        $user = User::query()->create([
            'name' => '2fa Continue',
            'email' => 'continue@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);
        $setup = OneAuth::enableTwoFactor(['method' => 'totp']);
        OneAuth::verifyTwoFactor([
            'code' => app(TotpService::class)->nowCode($setup['secret']),
        ]);
        Auth::logout();

        try {
            OneAuth::login([
                'email' => 'continue@example.com',
                'password' => 'Password123',
            ]);
            $this->fail('Expected TwoFactorRequiredException');
        } catch (TwoFactorRequiredException $e) {
            $code = app(TotpService::class)->nowCode($setup['secret']);
            $result = OneAuth::completeTwoFactorLogin([
                'challenge_token' => $e->getChallengeToken(),
                'code' => $code,
            ]);

            $this->assertTrue(Auth::check());
            $this->assertSame('continue@example.com', $result['user']->email);
            $this->assertTrue(session('oneauth.twofactor_verified'));
        }
    }

    public function test_failed_logins_consume_rate_limiter_budget_not_successes(): void
    {
        config([
            'oneauth.security.max_login_attempts' => 2,
            'oneauth.security.lockout_seconds' => 300,
        ]);

        OneAuth::register([
            'name' => 'Rate User',
            'email' => 'rate@example.com',
            'password' => 'Password123',
        ]);

        OneAuth::login(['email' => 'rate@example.com', 'password' => 'Password123']);
        OneAuth::logout();
        OneAuth::login(['email' => 'rate@example.com', 'password' => 'Password123']);
        OneAuth::logout();
        OneAuth::login(['email' => 'rate@example.com', 'password' => 'Password123']);
        OneAuth::logout();

        try {
            OneAuth::login(['email' => 'rate@example.com', 'password' => 'WrongPass1']);
            $this->fail('Expected AuthenticationException for invalid password');
        } catch (AuthenticationException $e) {
            $this->assertSame('Invalid credentials.', $e->getMessage());
        }

        try {
            OneAuth::login(['email' => 'rate@example.com', 'password' => 'WrongPass1']);
            $this->fail('Expected AuthenticationException for invalid password');
        } catch (AuthenticationException $e) {
            $this->assertSame('Invalid credentials.', $e->getMessage());
        }

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Too many login attempts');
        OneAuth::login(['email' => 'rate@example.com', 'password' => 'Password123']);
    }

    public function test_require_verified_email_blocks_unverified_users(): void
    {
        config(['oneauth.security.require_verified_email' => true]);

        OneAuth::register([
            'name' => 'Unverified',
            'email' => 'unverified@example.com',
            'password' => 'Password123',
        ]);

        $this->expectException(AuthenticationException::class);
        OneAuth::login([
            'email' => 'unverified@example.com',
            'password' => 'Password123',
        ]);
    }

    public function test_email_verification_marks_null_email_verified_at(): void
    {
        Mail::fake();

        $user = User::query()->create([
            'name' => 'Verify Me',
            'email' => 'verifyme@example.com',
            'password' => Hash::make('Password123'),
            'email_verified_at' => null,
        ]);
        Auth::login($user);

        $token = 'plain-verify-token';
        \Libinkk\OneAuth\Models\EmailVerification::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'email' => 'verifyme@example.com',
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->assertTrue(OneAuth::verifyEmail([
            'email' => 'verifyme@example.com',
            'token' => $token,
        ]));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_works_as_guest_with_token(): void
    {
        $user = User::query()->create([
            'name' => 'Guest Verify',
            'email' => 'guestverify@example.com',
            'password' => Hash::make('Password123'),
            'email_verified_at' => null,
        ]);

        $token = 'guest-verify-token';
        \Libinkk\OneAuth\Models\EmailVerification::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'email' => 'guestverify@example.com',
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->assertNull(Auth::user());
        $this->assertTrue(OneAuth::verifyEmail([
            'email' => 'guestverify@example.com',
            'token' => $token,
        ]));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_without_token_requires_auth(): void
    {
        $this->expectException(AuthenticationException::class);
        OneAuth::verifyEmail([
            'email' => 'someone@example.com',
        ]);
    }

    public function test_password_policy_rejects_weak_password(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        OneAuth::register([
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'password',
        ]);
    }

    public function test_default_api_login_route_works_without_session_store(): void
    {
        $this->postJson('/oneauth/register', [
            'name' => 'Api Session Bug',
            'email' => 'apisession@example.com',
            'password' => 'Password123',
        ])->assertCreated();

        $response = $this->postJson('/oneauth/login', [
            'email' => 'apisession@example.com',
            'password' => 'Password123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.email', 'apisession@example.com');
    }

    public function test_jwt_bearer_middleware_authenticates_protected_route(): void
    {
        config(['oneauth.driver' => 'jwt']);

        OneAuth::register([
            'name' => 'Jwt Bearer',
            'email' => 'bearer@example.com',
            'password' => 'Password123',
        ]);

        $login = OneAuth::login([
            'email' => 'bearer@example.com',
            'password' => 'Password123',
        ]);

        Auth::logout();

        $this->withHeader('Authorization', 'Bearer ' . $login['token'])
            ->getJson('/oneauth/user')
            ->assertOk()
            ->assertJsonPath('user.email', 'bearer@example.com');
    }

    public function test_jwt_refresh_route_works_without_auth(): void
    {
        config(['oneauth.driver' => 'jwt']);

        OneAuth::register([
            'name' => 'Jwt Refresh Route',
            'email' => 'jwtrefreshroute@example.com',
            'password' => 'Password123',
        ]);

        $login = OneAuth::login([
            'email' => 'jwtrefreshroute@example.com',
            'password' => 'Password123',
        ]);

        Auth::logout();

        $this->postJson('/oneauth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'refresh_token', 'user']);
    }

    public function test_otp_cooldown_allows_resend_after_window(): void
    {
        Mail::fake();

        $user = User::query()->create([
            'name' => 'Cooldowndown',
            'email' => 'cooldown@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);

        OneAuth::sendOtp([
            'purpose' => 'login',
            'target' => 'cooldown@example.com',
        ]);

        $this->travel(31)->seconds();

        $again = OneAuth::sendOtp([
            'purpose' => 'login',
            'target' => 'cooldown@example.com',
        ]);

        $this->assertArrayHasKey('id', $again);
    }

    public function test_protected_route_requires_auth(): void
    {
        $this->getJson('/oneauth/user')->assertUnauthorized();
    }

    public function test_social_login_establishes_session_and_jwt_tokens(): void
    {
        $this->app->bind('oneauth.oauth.google', fn () => new class implements \Libinkk\OneAuth\Contracts\OAuthProviderInterface {
            public function provider(): string
            {
                return 'google';
            }

            public function userFromToken(string $token): array
            {
                return [
                    'provider_id' => 'google-user-1',
                    'email' => 'social@example.com',
                    'name' => 'Social User',
                    'meta' => [],
                ];
            }
        });

        $sessionLogin = OneAuth::socialLogin('google', ['token' => 'opaque']);
        $this->assertTrue(Auth::check());
        $this->assertSame('social@example.com', $sessionLogin['user']->email);
        $this->assertNull($sessionLogin['token']);

        OneAuth::logout();
        config(['oneauth.driver' => 'jwt']);

        $jwtLogin = OneAuth::socialLogin('google', ['token' => 'opaque']);
        $this->assertNotEmpty($jwtLogin['token']);
        $this->assertNotEmpty($jwtLogin['refresh_token']);
        $this->assertSame('social@example.com', $jwtLogin['user']->email);
    }

    public function test_disable_two_factor_requires_reauthentication(): void
    {
        $user = User::query()->create([
            'name' => 'Disable 2fa',
            'email' => 'disable2fa@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);
        $setup = OneAuth::enableTwoFactor(['method' => 'totp']);
        OneAuth::verifyTwoFactor([
            'code' => app(TotpService::class)->nowCode($setup['secret']),
        ]);

        try {
            OneAuth::disableTwoFactor([]);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('required to disable 2FA', $e->getMessage());
        }

        $this->assertTrue(OneAuth::disableTwoFactor(['password' => 'Password123']));
        $this->assertDatabaseHas('oneauth_two_factor', [
            'authenticatable_id' => $user->getKey(),
            'enabled' => 0,
        ]);
    }

    public function test_jwt_logout_revokes_refresh_tokens(): void
    {
        config(['oneauth.driver' => 'jwt']);

        OneAuth::register([
            'name' => 'Jwt Logout',
            'email' => 'jwtlogout@example.com',
            'password' => 'Password123',
        ]);

        $login = OneAuth::login([
            'email' => 'jwtlogout@example.com',
            'password' => 'Password123',
        ]);

        OneAuth::logout();

        $active = RefreshToken::query()
            ->where('token_hash', hash('sha256', $login['refresh_token']))
            ->whereNull('revoked_at')
            ->exists();

        $this->assertFalse($active, 'JWT logout must revoke refresh tokens');

        request()->merge(['refresh_token' => $login['refresh_token']]);
        $this->expectException(AuthenticationException::class);
        OneAuth::refresh();
    }

    public function test_logout_revokes_oneauth_session_rows(): void
    {
        OneAuth::register([
            'name' => 'Session Revoke',
            'email' => 'sessionrevoke@example.com',
            'password' => 'Password123',
        ]);

        OneAuth::login([
            'email' => 'sessionrevoke@example.com',
            'password' => 'Password123',
        ]);

        $this->assertDatabaseCount('oneauth_sessions', 1);
        OneAuth::logout();
        $this->assertDatabaseCount('oneauth_sessions', 0);
    }

    public function test_device_first_login_at_is_stable_and_trusted_can_be_set(): void
    {
        OneAuth::register([
            'name' => 'Device User',
            'email' => 'device@example.com',
            'password' => 'Password123',
        ]);

        OneAuth::login([
            'email' => 'device@example.com',
            'password' => 'Password123',
        ]);

        $devices = OneAuth::devices();
        $this->assertCount(1, $devices);
        $firstLoginAt = $devices[0]['first_login_at'];
        $fingerprint = $devices[0]['fingerprint'];

        $this->travel(2)->minutes();
        OneAuth::logout();
        OneAuth::login([
            'email' => 'device@example.com',
            'password' => 'Password123',
        ]);

        $devices = OneAuth::devices();
        $this->assertSame($firstLoginAt, $devices[0]['first_login_at']);
        $this->assertTrue(OneAuth::trustDevice($fingerprint, true));
        $this->assertTrue((bool) OneAuth::devices()[0]['trusted']);
    }

    public function test_password_history_and_symbol_policy(): void
    {
        config(['oneauth.password_policy.require_symbol' => true]);

        try {
            OneAuth::register([
                'name' => 'Symbol User',
                'email' => 'symbol@example.com',
                'password' => 'Password123',
            ]);
            $this->fail('Expected ValidationException');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        config(['oneauth.password_policy.require_symbol' => false]);

        OneAuth::register([
            'name' => 'History User',
            'email' => 'history@example.com',
            'password' => 'Password123',
        ]);

        OneAuth::login([
            'email' => 'history@example.com',
            'password' => 'Password123',
        ]);

        try {
            OneAuth::changePassword([
                'current_password' => 'Password123',
                'new_password' => 'Password123',
            ]);
            $this->fail('Expected ValidationException for reused password');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        $this->assertTrue(OneAuth::changePassword([
            'current_password' => 'Password123',
            'new_password' => 'Password456',
        ]));
        $this->assertFalse(Auth::check(), 'Password change must invalidate the current session');
    }

    public function test_authentication_driver_interface_resolves_configured_driver(): void
    {
        config(['oneauth.driver' => 'jwt']);
        $driver = app(\Libinkk\OneAuth\Contracts\AuthenticationDriverInterface::class);
        $this->assertInstanceOf(\Libinkk\OneAuth\Drivers\JwtDriver::class, $driver);
    }

    public function test_doctor_fails_for_stub_otp_provider(): void
    {
        config(['oneauth.otp.provider' => 'sms']);
        $this->artisan('oneauth:doctor')->assertFailed();
    }

    public function test_otp_resend_limit_is_enforced(): void
    {
        Mail::fake();
        config([
            'oneauth.otp.resend_limit' => 1,
            'oneauth.otp.cooldown_seconds' => 0,
        ]);

        $user = User::query()->create([
            'name' => 'Resend User',
            'email' => 'resend@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);

        OneAuth::sendOtp([
            'purpose' => 'login',
            'target' => 'resend@example.com',
        ]);

        OneAuth::sendOtp([
            'purpose' => 'login',
            'target' => 'resend@example.com',
        ]);

        $this->expectException(OTPException::class);
        OneAuth::sendOtp([
            'purpose' => 'login',
            'target' => 'resend@example.com',
        ]);
    }

    public function test_unconfirmed_two_factor_does_not_gate_login(): void
    {
        $user = User::query()->create([
            'name' => 'Pending 2fa',
            'email' => 'pending2fa@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);
        OneAuth::enableTwoFactor(['method' => 'totp']);
        Auth::logout();

        $login = OneAuth::login([
            'email' => 'pending2fa@example.com',
            'password' => 'Password123',
        ]);

        $this->assertTrue(Auth::check());
        $this->assertSame('pending2fa@example.com', $login['user']->email);
    }

    public function test_resetting_enabled_two_factor_requires_step_up(): void
    {
        $user = User::query()->create([
            'name' => 'Reset 2fa',
            'email' => 'reset2fa@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);
        $setup = OneAuth::enableTwoFactor(['method' => 'totp']);
        OneAuth::verifyTwoFactor([
            'code' => app(TotpService::class)->nowCode($setup['secret']),
        ]);

        try {
            OneAuth::enableTwoFactor(['method' => 'totp']);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('required to reset 2FA', $e->getMessage());
        }

        $reset = OneAuth::enableTwoFactor([
            'method' => 'totp',
            'password' => 'Password123',
        ]);
        $this->assertFalse($reset['confirmed']);
        $this->assertDatabaseHas('oneauth_two_factor', [
            'authenticatable_id' => $user->getKey(),
            'enabled' => 0,
        ]);
    }

    public function test_pending_two_factor_cannot_confirm_with_recovery_code(): void
    {
        $user = User::query()->create([
            'name' => 'Pending Confirm',
            'email' => 'pendingconfirm@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);
        $setup = OneAuth::enableTwoFactor(['method' => 'totp']);

        $this->expectException(AuthenticationException::class);
        OneAuth::verifyTwoFactor([
            'recovery_code' => $setup['recovery_codes'][0],
        ]);
    }

    public function test_two_factor_challenge_survives_wrong_code(): void
    {
        $user = User::query()->create([
            'name' => 'Challenge Typo',
            'email' => 'challengetypo@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);
        $setup = OneAuth::enableTwoFactor(['method' => 'totp']);
        OneAuth::verifyTwoFactor([
            'code' => app(TotpService::class)->nowCode($setup['secret']),
        ]);
        Auth::logout();

        try {
            OneAuth::login([
                'email' => 'challengetypo@example.com',
                'password' => 'Password123',
            ]);
            $this->fail('Expected TwoFactorRequiredException');
        } catch (TwoFactorRequiredException $e) {
            $token = $e->getChallengeToken();

            try {
                OneAuth::completeTwoFactorLogin([
                    'challenge_token' => $token,
                    'code' => '000000',
                ]);
                $this->fail('Expected invalid code');
            } catch (AuthenticationException $invalid) {
                $this->assertStringContainsString('invalid', strtolower($invalid->getMessage()));
            }

            $result = OneAuth::completeTwoFactorLogin([
                'challenge_token' => $token,
                'code' => app(TotpService::class)->nowCode($setup['secret']),
            ]);

            $this->assertTrue(Auth::check());
            $this->assertSame('challengetypo@example.com', $result['user']->email);
        }
    }

    public function test_otp_can_restart_after_expiry_even_if_resend_limit_hit(): void
    {
        Mail::fake();
        config([
            'oneauth.otp.resend_limit' => 0,
            'oneauth.otp.cooldown_seconds' => 0,
            'oneauth.otp.expires_in_seconds' => 30,
        ]);

        $user = User::query()->create([
            'name' => 'Expiry Restart',
            'email' => 'otpexpiry@example.com',
            'password' => Hash::make('Password123'),
        ]);
        Auth::login($user);

        OneAuth::sendOtp([
            'purpose' => 'login',
            'target' => 'otpexpiry@example.com',
        ]);

        try {
            OneAuth::sendOtp([
                'purpose' => 'login',
                'target' => 'otpexpiry@example.com',
            ]);
            $this->fail('Expected OTP resend limit');
        } catch (OTPException $e) {
            $this->assertStringContainsString('resend limit', strtolower($e->getMessage()));
        }

        $this->travel(31)->seconds();

        $again = OneAuth::sendOtp([
            'purpose' => 'login',
            'target' => 'otpexpiry@example.com',
        ]);
        $this->assertArrayHasKey('id', $again);
    }

    public function test_social_login_does_not_auto_link_unlinked_email_by_default(): void
    {
        User::query()->create([
            'name' => 'Existing',
            'email' => 'taken@example.com',
            'password' => Hash::make('Password123'),
            'email_verified_at' => now(),
        ]);

        $this->app->bind('oneauth.oauth.google', fn () => new class implements \Libinkk\OneAuth\Contracts\OAuthProviderInterface {
            public function provider(): string
            {
                return 'google';
            }

            public function userFromToken(string $token): array
            {
                return [
                    'provider_id' => 'google-taken-1',
                    'email' => 'taken@example.com',
                    'name' => 'Taken Social',
                    'meta' => [],
                ];
            }
        });

        $this->expectException(\Libinkk\OneAuth\Exceptions\OneAuthException::class);
        OneAuth::socialLogin('google', ['token' => 'opaque']);
    }

    public function test_forgot_password_hides_email_enumeration(): void
    {
        $known = OneAuth::forgotPassword(['email' => 'missing-user@example.com']);
        $this->assertSame(\Illuminate\Support\Facades\Password::RESET_LINK_SENT, $known);
    }

    public function test_jwt_access_token_is_rejected_after_logout(): void
    {
        config(['oneauth.driver' => 'jwt']);

        OneAuth::register([
            'name' => 'Jwt Access Revoke',
            'email' => 'jwtaccess@example.com',
            'password' => 'Password123',
        ]);

        $login = OneAuth::login([
            'email' => 'jwtaccess@example.com',
            'password' => 'Password123',
        ]);

        OneAuth::logout();

        $this->withHeader('Authorization', 'Bearer ' . $login['token'])
            ->getJson('/oneauth/user')
            ->assertUnauthorized();
    }

    public function test_logout_only_revokes_current_tracked_session_row(): void
    {
        OneAuth::register([
            'name' => 'Multi Session',
            'email' => 'multisession@example.com',
            'password' => 'Password123',
        ]);

        OneAuth::login([
            'email' => 'multisession@example.com',
            'password' => 'Password123',
        ]);

        \Libinkk\OneAuth\Models\Session::query()->create([
            'authenticatable_type' => User::class,
            'authenticatable_id' => Auth::id(),
            'session_id' => 'other-device-session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Other',
            'last_activity_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertDatabaseCount('oneauth_sessions', 2);
        OneAuth::logout();
        $this->assertDatabaseCount('oneauth_sessions', 1);
        $this->assertDatabaseHas('oneauth_sessions', [
            'session_id' => 'other-device-session',
        ]);
    }
}
