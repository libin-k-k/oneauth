<?php

namespace Libinkk\OneAuth\Support;

use Libinkk\OneAuth\Events\AccountLocked;
use Libinkk\OneAuth\Events\EmailVerified;
use Libinkk\OneAuth\Events\OTPSent;
use Libinkk\OneAuth\Events\OTPVerified;
use Libinkk\OneAuth\Events\PasswordChanged;
use Libinkk\OneAuth\Events\PasswordReset;
use Libinkk\OneAuth\Events\SocialLogin;
use Libinkk\OneAuth\Events\SuspiciousLoginDetected;
use Libinkk\OneAuth\Events\TwoFactorDisabled;
use Libinkk\OneAuth\Events\TwoFactorEnabled;
use Libinkk\OneAuth\Events\UserLoggedIn;
use Libinkk\OneAuth\Events\UserLoggedOut;
use Libinkk\OneAuth\Events\UserRegistered;
use Libinkk\OneAuth\Models\AuditLog;

class AuditLogger
{
    public function handle(object $event): void
    {
        if (!(bool) config('oneauth.logging.audit_enabled', true)) {
            return;
        }

        if (!property_exists($event, 'user') || !$event->user) {
            return;
        }

        $user = $event->user;
        $context = property_exists($event, 'context') && is_array($event->context)
            ? $this->sanitizeContext($event->context)
            : [];

        if ($event instanceof SocialLogin && isset($context['provider'])) {
            $context = ['provider' => $context['provider']];
        }

        AuditLog::query()->create([
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getKey(),
            'event' => $this->eventName($event),
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }

    protected function eventName(object $event): string
    {
        return match (true) {
            $event instanceof UserRegistered => 'user.registered',
            $event instanceof UserLoggedIn => 'user.logged_in',
            $event instanceof UserLoggedOut => 'user.logged_out',
            $event instanceof EmailVerified => 'email.verified',
            $event instanceof OTPSent => 'otp.sent',
            $event instanceof OTPVerified => 'otp.verified',
            $event instanceof PasswordChanged => 'password.changed',
            $event instanceof PasswordReset => 'password.reset',
            $event instanceof TwoFactorEnabled => 'two_factor.enabled',
            $event instanceof TwoFactorDisabled => 'two_factor.disabled',
            $event instanceof SocialLogin => 'social.login',
            $event instanceof SuspiciousLoginDetected => 'login.suspicious',
            $event instanceof AccountLocked => 'account.locked',
            default => class_basename($event),
        };
    }

    protected function sanitizeContext(array $context): array
    {
        $blocked = [
            'password',
            'token',
            'refresh_token',
            'code',
            'recovery_code',
            'secret',
            'challenge_token',
            'access_token',
        ];

        foreach ($blocked as $key) {
            unset($context[$key]);
        }

        return $context;
    }
}
