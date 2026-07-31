<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Password;

class RequestPasswordResetAction
{
    public function execute(array $payload): string
    {
        $email = (string) ($payload['email'] ?? '');
        Password::sendResetLink(['email' => $email]);

        // Always return the success status to avoid email enumeration.
        return Password::RESET_LINK_SENT;
    }
}
