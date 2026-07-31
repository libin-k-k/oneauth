<?php

namespace Libinkk\OneAuth\Commands;

use Illuminate\Console\Command;
use Libinkk\OneAuth\Models\AccountLock;
use Libinkk\OneAuth\Models\LoginAttempt;
use Libinkk\OneAuth\Models\Otp;
use Libinkk\OneAuth\Models\RefreshToken;
use Libinkk\OneAuth\Repositories\SessionRepository;

class CleanupCommand extends Command
{
    protected $signature = 'oneauth:cleanup {--attempts-days=30 : Delete login attempts older than this many days}';
    protected $description = 'Cleanup expired OneAuth records';

    public function handle(SessionRepository $sessions): int
    {
        $expiredOtps = Otp::query()->where('expires_at', '<', now())->delete();
        $expiredRefresh = RefreshToken::query()->where('expires_at', '<', now())->orWhereNotNull('revoked_at')->delete();
        $expiredSessions = $sessions->cleanupExpired();
        $expiredLocks = AccountLock::query()
            ->whereNotNull('locked_until')
            ->where('locked_until', '<', now())
            ->delete();

        $days = max(1, (int) $this->option('attempts-days'));
        $oldAttempts = LoginAttempt::query()
            ->where('attempted_at', '<', now()->subDays($days))
            ->delete();

        $this->info('Expired OTP records: ' . $expiredOtps);
        $this->info('Expired refresh tokens: ' . $expiredRefresh);
        $this->info('Expired sessions: ' . $expiredSessions);
        $this->info('Expired account locks: ' . $expiredLocks);
        $this->info('Old login attempts: ' . $oldAttempts);

        return self::SUCCESS;
    }
}
