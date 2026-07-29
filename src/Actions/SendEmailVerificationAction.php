<?php

namespace Libinkk\OneAuth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Libinkk\OneAuth\Exceptions\AuthenticationException;
use Libinkk\OneAuth\Models\EmailVerification;

class SendEmailVerificationAction
{
    public function execute(array $payload): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User is not authenticated.');
        }

        $email = (string) ($payload['email'] ?? $user->email ?? '');
        $token = Str::random(64);

        $row = EmailVerification::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'email' => $email,
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addMinutes(30),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'oneauth.email.verify.signed',
            now()->addMinutes(30),
            ['email' => $email, 'token' => $token]
        );

        Mail::raw('Your email verification token is: ' . $token . PHP_EOL . 'Signed URL: ' . $signedUrl, function ($message) use ($email): void {
            $message->to($email)->subject('Verify your email');
        });

        return ['id' => $row->id, 'expires_at' => $row->expires_at, 'signed_url' => $signedUrl];
    }
}
