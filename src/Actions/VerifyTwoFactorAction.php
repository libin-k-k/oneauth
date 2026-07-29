<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;
use Libinkk\OneAuth\Support\TotpService;

class VerifyTwoFactorAction
{
    public function __construct(private TotpService $totp)
    {
    }

    public function execute(array $payload): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $twoFactor = TwoFactor::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('enabled', true)
            ->first();

        if (!$twoFactor) {
            return true;
        }

        $code = (string) ($payload['code'] ?? '');
        $recovery = (string) ($payload['recovery_code'] ?? '');

        if ($recovery !== '') {
            $hash = hash('sha256', $recovery);
            $codes = (array) $twoFactor->recovery_codes;
            $index = array_search($hash, $codes, true);
            if ($index === false) {
                throw new AuthenticationException('Recovery code is invalid.');
            }
            unset($codes[$index]);
            $twoFactor->update(['recovery_codes' => array_values($codes)]);
            session(['oneauth.twofactor_verified' => true]);
            return true;
        }

        $secret = Crypt::decryptString((string) $twoFactor->secret_encrypted);
        if (!$this->totp->verify($secret, $code)) {
            throw new AuthenticationException('Two-factor code is invalid.');
        }

        session(['oneauth.twofactor_verified' => true]);
        return true;
    }
}
