<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Exceptions\OneAuthException;
use Libinkk\OneAuth\Models\TwoFactor;
use Libinkk\OneAuth\Support\OtpService;
use Libinkk\OneAuth\Support\TotpService;
use Libinkk\OneAuth\Support\TwoFactorCodeVerifier;

class EnableTwoFactorAction
{
    public function __construct(
        private TotpService $totp,
        private TwoFactorCodeVerifier $verifier,
        private OtpService $otp
    ) {
    }

    public function execute(array $payload): array
    {
        if (!(bool) config('oneauth.two_factor.enabled', true)) {
            throw new OneAuthException('Two-factor authentication is disabled.');
        }

        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $method = strtolower((string) ($payload['method'] ?? 'totp'));
        if (!in_array($method, ['totp', 'email', 'sms'], true)) {
            throw new OneAuthException('Unsupported two-factor method: ' . $method);
        }

        $existing = TwoFactor::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('enabled', true)
            ->first();

        if ($existing) {
            $password = (string) ($payload['password'] ?? '');
            $code = (string) ($payload['code'] ?? '');
            $recovery = (string) ($payload['recovery_code'] ?? '');

            if ($password === '' && $code === '' && $recovery === '') {
                throw new AuthenticationException('Password or two-factor code is required to reset 2FA.');
            }

            if ($password !== '') {
                if (!Hash::check($password, (string) $user->password)) {
                    throw new AuthenticationException('Current password is invalid.');
                }
            } else {
                $this->verifier->verifyOrFail($user, $payload, enabledOnly: true);
            }
        }

        $codes = [];
        for ($i = 0; $i < (int) config('oneauth.two_factor.recovery_codes_count', 8); $i++) {
            $codes[] = Str::upper(Str::random(10));
        }

        $secret = null;
        $otpChallenge = null;

        if ($method === 'totp') {
            $secret = $this->totp->generateSecret();
        } else {
            $target = $method === 'sms'
                ? (string) ($user->phone ?? '')
                : (string) ($user->email ?? '');

            if ($target === '') {
                throw new AuthenticationException(
                    $method === 'sms'
                        ? 'Phone number is required for SMS two-factor authentication.'
                        : 'Email is required for email two-factor authentication.'
                );
            }

            $otpChallenge = $this->otp->send(
                $user,
                'two_factor',
                $method === 'sms' ? 'sms' : 'email',
                $target
            );
        }

        TwoFactor::query()->updateOrCreate(
            ['authenticatable_type' => $user::class, 'authenticatable_id' => $user->getKey()],
            [
                'enabled' => false,
                'method' => $method,
                'secret_encrypted' => $secret !== null ? Crypt::encryptString($secret) : null,
                'recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes),
                'enabled_at' => null,
            ]
        );

        $account = (string) ($user->email ?? $user->name ?? $user->getKey());
        $result = [
            'method' => $method,
            'recovery_codes' => $codes,
            'confirmed' => false,
        ];

        if ($method === 'totp') {
            $result['secret'] = $secret;
            $result['otpauth_uri'] = $this->totp->otpauthUri($secret, $account);
            $result['issuer'] = (string) config('oneauth.two_factor.totp_issuer', 'OneAuth');
        } else {
            $result['otp'] = $otpChallenge;
        }

        return $result;
    }
}
