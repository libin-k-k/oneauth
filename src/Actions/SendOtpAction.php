<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\OTPSent;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\OtpService;
use Libinkk\OneAuth\Support\UserResolver;

class SendOtpAction
{
    public function __construct(private OtpService $otpService)
    {
    }

    public function execute(array $payload): array
    {
        $user = Auth::user();
        $identifier = (string) (
            $payload['identifier']
            ?? $payload['email']
            ?? $payload['username']
            ?? $payload['phone']
            ?? ''
        );

        if (!$user && $identifier !== '') {
            $user = UserResolver::queryByIdentifiers($identifier);
        }

        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $purpose = (string) ($payload['purpose'] ?? 'login');
        $channel = (string) ($payload['channel'] ?? config('oneauth.otp.provider', 'email'));
        $target = (string) (
            $payload['target']
            ?? ($channel === 'sms' ? ($user->phone ?? '') : ($user->email ?? $identifier))
        );

        if ($target === '') {
            throw new AuthenticationException('OTP target is required.');
        }

        $result = $this->otpService->send($user, $purpose, $channel, $target);
        Event::dispatch(new OTPSent($user, ['purpose' => $purpose, 'channel' => $channel]));

        return $result;
    }
}
