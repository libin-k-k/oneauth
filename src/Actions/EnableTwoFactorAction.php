<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Events\TwoFactorEnabled;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\TwoFactor;
use Libinkk\OneAuth\Support\TotpService;

class EnableTwoFactorAction
{
    public function __construct(private TotpService $totp)
    {
    }

    public function execute(array $payload): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $secret = $this->totp->generateSecret();
        $codes = [];
        for ($i = 0; $i < (int) config('oneauth.two_factor.recovery_codes_count', 8); $i++) {
            $codes[] = Str::upper(Str::random(10));
        }

        TwoFactor::query()->updateOrCreate(
            ['authenticatable_type' => $user::class, 'authenticatable_id' => $user->getKey()],
            [
                'enabled' => true,
                'method' => (string) ($payload['method'] ?? 'totp'),
                'secret_encrypted' => Crypt::encryptString($secret),
                'recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes),
                'enabled_at' => now(),
            ]
        );

        Event::dispatch(new TwoFactorEnabled($user));
        return ['secret' => $secret, 'recovery_codes' => $codes];
    }
}
