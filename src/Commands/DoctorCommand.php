<?php

namespace Libinkk\OneAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DoctorCommand extends Command
{
    protected $signature = 'oneauth:doctor';

    protected $description = 'Run OneAuth health checks';

    protected array $tables = [
        'oneauth_otps',
        'oneauth_devices',
        'oneauth_sessions',
        'oneauth_social_accounts',
        'oneauth_email_verifications',
        'oneauth_two_factor',
        'oneauth_login_attempts',
        'oneauth_password_history',
        'oneauth_refresh_tokens',
        'oneauth_audit_logs',
    ];

    public function handle(): int
    {
        $this->line('Driver: ' . config('oneauth.driver'));
        $this->line('Routes enabled: ' . (config('oneauth.routes.enabled') ? 'yes' : 'no'));
        $this->line('OTP provider: ' . config('oneauth.otp.provider'));
        $this->line('2FA enabled: ' . (config('oneauth.two_factor.enabled') ? 'yes' : 'no'));

        $this->newLine();
        $this->line('OneAuth table status:');

        $missing = [];

        foreach ($this->tables as $table) {
            $exists = Schema::hasTable($table);
            $this->line(($exists ? '[exists] ' : '[missing] ') . $table);

            if (!$exists) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            $this->newLine();
            $this->error('Missing tables: ' . implode(', ', $missing));
            $this->warn('Run: php artisan migrate');
            $this->warn('Migrations skip tables that already exist, so re-running is safe.');

            return self::FAILURE;
        }

        $this->info('OneAuth doctor checks completed.');

        return self::SUCCESS;
    }
}
