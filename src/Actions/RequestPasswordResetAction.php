<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Password;

class RequestPasswordResetAction
{
    public function execute(array $payload): string
    {
        $email = (string) ($payload['email'] ?? '');
        return Password::sendResetLink(['email' => $email]);
    }
}
