<?php

namespace Libinkk\OneAuth\Providers\OTP;

use Illuminate\Support\Facades\Mail;
use Libinkk\OneAuth\Contracts\OTPProviderInterface;

class EmailOtpProvider implements OTPProviderInterface
{
    public function send(string $channel, string $to, string $code, array $context = []): void
    {
        Mail::raw(
            'Your OneAuth OTP is: ' . $code . '. It expires in ' . (int) (config('oneauth.otp.expires_in_seconds', 300) / 60) . ' minutes.',
            function ($message) use ($to): void {
                $message->to($to)->subject('Your OneAuth OTP');
            }
        );
    }
}
