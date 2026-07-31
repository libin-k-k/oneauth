<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\OTPVerified;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\OtpService;

class VerifyOtpAction
{
    public function __construct(private OtpService $otpService)
    {
    }

    public function execute(array $payload): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $purpose = (string) ($payload['purpose'] ?? 'login');

        $ok = $this->otpService->verify(
            $user,
            $purpose,
            (string) ($payload['target'] ?? $user->email ?? ''),
            (string) ($payload['code'] ?? '')
        );

        if ($ok) {
            Event::dispatch(new OTPVerified($user));
            if (request()->hasSession()) {
                session(['oneauth.otp_verified' => $purpose]);
            }
        }

        return $ok;
    }
}
