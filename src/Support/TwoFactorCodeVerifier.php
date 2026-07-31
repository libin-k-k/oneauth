<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;

class TwoFactorCodeVerifier
{
    public function __construct(private TotpService $totp)
    {
    }

    public function verifyOrFail(
        mixed $user,
        array $payload,
        bool $enabledOnly = true,
        bool $allowRecovery = true
    ): TwoFactor {
        $query = TwoFactor::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->whereNotNull('secret_encrypted');

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

        $secret = Crypt::decryptString((string) $twoFactor->secret_encrypted);
        if (!$this->totp->verify($secret, $code)) {
            throw new AuthenticationException('Two-factor code is invalid.');
        }

        return $twoFactor;
    }
}
