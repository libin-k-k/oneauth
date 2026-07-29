<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\OTPSent;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\OtpService;

class SendOtpAction
{
    public function __construct(private OtpService $otpService)
    {
    }

    public function execute(array $payload): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $purpose = (string) ($payload['purpose'] ?? 'login');
        $channel = (string) ($payload['channel'] ?? config('oneauth.otp.provider', 'email'));
        $target = (string) ($payload['target'] ?? $user->email ?? '');
        $result = $this->otpService->send($user, $purpose, $channel, $target);
        Event::dispatch(new OTPSent($user, ['purpose' => $purpose, 'channel' => $channel]));

        return $result;
    }
}
