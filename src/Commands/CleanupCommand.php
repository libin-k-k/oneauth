<?php

namespace Libinkk\OneAuth\Commands;

use Illuminate\Console\Command;
use Libinkk\OneAuth\Models\Otp;
use Libinkk\OneAuth\Models\RefreshToken;
use Libinkk\OneAuth\Repositories\SessionRepository;

class CleanupCommand extends Command
{
    protected $signature = 'oneauth:cleanup';
    protected $description = 'Cleanup expired OneAuth records';

    public function handle(SessionRepository $sessions): int
    {
        $expiredOtps = Otp::query()->where('expires_at', '<', now())->delete();
        $expiredRefresh = RefreshToken::query()->where('expires_at', '<', now())->orWhereNotNull('revoked_at')->delete();
        $expiredSessions = $sessions->cleanupExpired();

        $this->info('Expired OTP records: ' . $expiredOtps);
        $this->info('Expired refresh tokens: ' . $expiredRefresh);
        $this->info('Expired sessions: ' . $expiredSessions);

        return self::SUCCESS;
    }
}
