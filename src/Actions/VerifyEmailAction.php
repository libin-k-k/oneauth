<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Events\EmailVerified;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\EmailVerification;

class VerifyEmailAction
{
    public function execute(array $payload): bool
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $token = (string) ($payload['token'] ?? '');
        $email = (string) ($payload['email'] ?? $user->email ?? '');
        $row = EmailVerification::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getKey())
            ->where('email', $email)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$row || ($row->expires_at && $row->expires_at->isPast())) {
            return false;
        }

        if ($row->token_hash && !Hash::check($token, $row->token_hash)) {
            return false;
        }

        $row->update(['verified_at' => now()]);
        if (isset($user->email_verified_at)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Event::dispatch(new EmailVerified($user));
        return true;
    }
}
