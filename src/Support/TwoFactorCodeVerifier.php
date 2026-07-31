<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;

class TwoFactorCodeVerifier
{
    public function __construct(
        private TotpService $totp,
        private OtpService $otp
    ) {
    }

    public function verifyOrFail(
        mixed $user,
        array $payload,
        bool $enabledOnly = true,
        bool $allowRecovery = true
    ): TwoFactor {
        $query = TwoFactor::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey());

        if ($enabledOnly) {
            $query->where('enabled', true);
        }

        $twoFactor = $query->first();

        if (!$twoFactor) {
            throw new AuthenticationException(
                $enabledOnly
                    ? 'Two-factor authentication is not enabled.'
                    : 'Two-factor authentication is not set up.'
            );
        }

        $code = (string) ($payload['code'] ?? '');
        $recovery = (string) ($payload['recovery_code'] ?? '');

        if ($recovery !== '') {
            if (!$allowRecovery) {
                throw new AuthenticationException('Confirm two-factor setup with an authenticator code.');
            }

            $hash = hash('sha256', Str::upper(trim($recovery)));
            $codes = (array) $twoFactor->recovery_codes;
            $index = array_search($hash, $codes, true);
            if ($index === false) {
                throw new AuthenticationException('Recovery code is invalid.');
            }

            unset($codes[$index]);
            $twoFactor->update(['recovery_codes' => array_values($codes)]);

            return $twoFactor->fresh();
        }

        if ($code === '') {
            throw new AuthenticationException('Two-factor code is required.');
        }

        $method = (string) ($twoFactor->method ?? 'totp');

        if (in_array($method, ['email', 'sms'], true)) {
            $target = $method === 'sms'
                ? (string) ($user->phone ?? $payload['target'] ?? '')
                : (string) ($user->email ?? $payload['target'] ?? '');

            if ($target === '') {
                throw new AuthenticationException('Two-factor OTP target is missing.');
            }

            try {
                $this->otp->verify($user, 'two_factor', $target, $code);
            } catch (\Throwable $throwable) {
                throw new AuthenticationException('Two-factor code is invalid.');
            }

            return $twoFactor;
        }

        if (!$twoFactor->secret_encrypted) {
            throw new AuthenticationException('Two-factor authentication is not set up.');
        }

        $secret = Crypt::decryptString((string) $twoFactor->secret_encrypted);
        if (!$this->totp->verify($secret, $code)) {
            throw new AuthenticationException('Two-factor code is invalid.');
        }

        return $twoFactor;
    }
}
