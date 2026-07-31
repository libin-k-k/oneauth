<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;
use Libinkk\OneAuth\Support\TotpService;
use Libinkk\OneAuth\Support\TwoFactorCodeVerifier;

class EnableTwoFactorAction
{
    public function __construct(
        private TotpService $totp,
        private TwoFactorCodeVerifier $verifier
    ) {
    }

    public function execute(array $payload): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
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

        $secret = $this->totp->generateSecret();
        $codes = [];
        for ($i = 0; $i < (int) config('oneauth.two_factor.recovery_codes_count', 8); $i++) {
            $codes[] = Str::upper(Str::random(10));
        }

        TwoFactor::query()->updateOrCreate(
            ['authenticatable_type' => $user::class, 'authenticatable_id' => $user->getKey()],
            [
                'enabled' => false,
                'method' => (string) ($payload['method'] ?? 'totp'),
                'secret_encrypted' => Crypt::encryptString($secret),
                'recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes),
                'enabled_at' => null,
            ]
        );

        return [
            'secret' => $secret,
            'recovery_codes' => $codes,
            'confirmed' => false,
        ];
    }
}
