<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Contracts\OTPProviderInterface;
use Libinkk\OneAuth\Exceptions\OTPException;
use Libinkk\OneAuth\Models\Otp;

class OtpService
{
    public function __construct(private OTPProviderInterface $provider)
    {
    }

    public function send(mixed $user, string $purpose, string $channel, string $target): array
    {
        $recent = Otp::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('purpose', $purpose)
            ->where('target', $target)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($recent && $recent->expires_at && $recent->expires_at->isPast()) {
            $recent->delete();
            $recent = null;
        }

        if ($recent && $recent->last_sent_at) {
            $elapsedSeconds = (int) $recent->last_sent_at->diffInSeconds(now(), true);
            if ($elapsedSeconds < (int) config('oneauth.otp.cooldown_seconds', 30)) {
                throw new OTPException('OTP cooldown is active.');
            }

            $resendLimit = (int) config('oneauth.otp.resend_limit', 3);
            if ($recent->resends >= $resendLimit) {
                throw new OTPException('OTP resend limit reached.');
            }
        }

        $code = $this->generateCode();
        $expiresAt = now()->addSeconds((int) config('oneauth.otp.expires_in_seconds', 300));

        if ($recent) {
            $recent->update([
                'channel' => $channel,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'resends' => ((int) $recent->resends) + 1,
                'expires_at' => $expiresAt,
                'last_sent_at' => now(),
            ]);
            $otp = $recent->fresh();
        } else {
            $otp = Otp::query()->create([
                'authenticatable_type' => $user::class,
                'authenticatable_id' => $user->getKey(),
                'purpose' => $purpose,
                'channel' => $channel,
                'target' => $target,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'resends' => 0,
                'expires_at' => $expiresAt,
                'last_sent_at' => now(),
                'meta' => ['type' => config('oneauth.otp.type', 'numeric')],
            ]);
        }

        $this->provider->send($channel, $target, $code, ['purpose' => $purpose]);

        return ['id' => $otp->id, 'expires_at' => $otp->expires_at];
    }

    public function verify(mixed $user, string $purpose, string $target, string $code): bool
    {
        $otp = Otp::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('purpose', $purpose)
            ->where('target', $target)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$otp || $otp->expires_at->isPast()) {
            throw new OTPException('OTP is invalid or expired.');
        }

        if ($otp->attempts >= (int) config('oneauth.otp.max_attempts', 5)) {
            throw new OTPException('OTP attempt limit reached.');
        }

        $otp->increment('attempts');

        if (!Hash::check($code, $otp->code_hash)) {
            throw new OTPException('OTP code is invalid.');
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }

    protected function generateCode(): string
    {
        $length = max(4, (int) config('oneauth.otp.length', 6));
        $type = (string) config('oneauth.otp.type', 'numeric');

        if ($type === 'alphanumeric') {
            return Str::upper(Str::random($length));
        }

        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
