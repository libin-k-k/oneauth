<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Libinkk\OneAuth\Events\EmailVerified;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\EmailVerification;
use Libinkk\OneAuth\Support\EmailVerificationStatus;

class VerifyEmailAction
{
    public function execute(array $payload): bool
    {
        $token = (string) ($payload['token'] ?? '');
        $email = (string) ($payload['email'] ?? '');
        $user = Auth::user();

        $row = null;

        if ($user) {
            $email = $email !== '' ? $email : (string) ($user->email ?? '');
            $row = EmailVerification::query()
                ->where('authenticatable_type', $user::class)
                ->where('authenticatable_id', $user->getKey())
                ->where('email', $email)
                ->whereNull('verified_at')
                ->latest('id')
                ->first();
        } elseif ($email !== '' && $token !== '') {
            $candidates = EmailVerification::query()
                ->where('email', $email)
                ->whereNull('verified_at')
                ->latest('id')
                ->get();

            foreach ($candidates as $candidate) {
                if ($candidate->token_hash && Hash::check($token, $candidate->token_hash)) {
                    $row = $candidate;
                    break;
                }
            }

            if ($row) {
                $userClass = $row->authenticatable_type;
                $user = $userClass::query()->find($row->authenticatable_id);
            }
        } else {
            throw new AuthenticationException('Email verification requires authentication or a valid email token.');
        }

        if (!$user || !$row || ($row->expires_at && $row->expires_at->isPast())) {
            return false;
        }

        if ($row->token_hash && !Hash::check($token, $row->token_hash)) {
            return false;
        }

        $row->update(['verified_at' => now()]);
        EmailVerificationStatus::markVerified($user);
        Event::dispatch(new EmailVerified($user));

        return true;
    }
}
