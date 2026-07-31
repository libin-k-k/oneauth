<?php

namespace Libinkk\OneAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Libinkk\OneAuth\Providers\OTP\SmsOtpProvider;
use Libinkk\OneAuth\Providers\OTP\WhatsAppOtpProvider;

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
        $failed = false;

        $this->line('Driver: ' . config('oneauth.driver'));
        $this->line('Routes enabled: ' . (config('oneauth.routes.enabled') ? 'yes' : 'no'));
        $this->line('OTP provider: ' . config('oneauth.otp.provider'));
        $this->line('2FA enabled: ' . (config('oneauth.two_factor.enabled') ? 'yes' : 'no'));

        $userModel = (string) config('oneauth.user_model');
        if ($userModel === '' || !class_exists($userModel)) {
            $this->error('User model is missing or invalid: ' . ($userModel ?: '(empty)'));
            $failed = true;
        } else {
            $this->line('User model: ' . $userModel);
        }

        $driver = (string) config('oneauth.driver', 'session');
        if ($driver === 'sanctum' && !class_exists(\Laravel\Sanctum\HasApiTokens::class)) {
            $this->error('Driver is sanctum but laravel/sanctum is not installed.');
            $failed = true;
        }

        if ($driver === 'jwt') {
            $secret = (string) config('oneauth.jwt.secret');
            if ($secret === '') {
                $this->error('JWT secret is empty. Set ONEAUTH_JWT_SECRET.');
                $failed = true;
            }
        }

        $otpProvider = (string) config('oneauth.otp.provider', 'email');
        if (in_array($otpProvider, ['sms', 'whatsapp'], true)) {
            $bound = app(\Libinkk\OneAuth\Contracts\OTPProviderInterface::class);
            if ($bound instanceof SmsOtpProvider || $bound instanceof WhatsAppOtpProvider) {
                $this->error(
                    'OTP provider "' . $otpProvider . '" is still the package stub. Bind a real OTPProviderInterface implementation before using it in production.'
                );
                $failed = true;
            }
        }

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
            $failed = true;
        }

        if ($failed) {
            $this->newLine();
            $this->error('OneAuth doctor found configuration or schema problems.');

            return self::FAILURE;
        }

        $this->info('OneAuth doctor checks completed.');

        return self::SUCCESS;
    }
}
