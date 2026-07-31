<?php

namespace Libinkk\OneAuth\Providers\OTP;

use Libinkk\OneAuth\Contracts\OTPProviderInterface;
use Libinkk\OneAuth\Exceptions\OneAuthException;

class WhatsAppOtpProvider implements OTPProviderInterface
{
    public function send(string $channel, string $to, string $code, array $context = []): void
    {
        throw new OneAuthException(
            'WhatsApp OTP provider is a contract stub. Bind your own OTPProviderInterface implementation for oneauth.otp.provider=whatsapp, or switch to email. Run php artisan oneauth:doctor for details.'
        );
    }
}
