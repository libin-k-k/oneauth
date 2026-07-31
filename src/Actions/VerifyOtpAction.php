<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Libinkk\OneAuth\Events\OTPVerified;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Support\OtpService;
use Libinkk\OneAuth\Support\UserResolver;

class VerifyOtpAction
{
    public function __construct(private OtpService $otpService)
    {
    }

    public function execute(array $payload): bool
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
        $target = (string) ($payload['target'] ?? $user->email ?? $user->phone ?? $identifier);

        $ok = $this->otpService->verify(
            $user,
            $purpose,
            $target,
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
