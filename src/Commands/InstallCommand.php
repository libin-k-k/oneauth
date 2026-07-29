<?php

namespace Libinkk\OneAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'oneauth:install
                            {--migrate : Run package migrations after publish}
                            {--force : Overwrite published files if they already exist}';

    protected $description = 'Install OneAuth configuration and migrations safely';

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
        $this->publishConfig();
        $this->publishMigrations();

        if ($this->option('migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->reportTableStatus();

        $this->info('OneAuth install completed.');

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $target = config_path('oneauth.php');

        if (File::exists($target) && !$this->option('force')) {
            $this->line('Config already exists: config/oneauth.php (skipped)');

            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'oneauth-config',
            '--force' => (bool) $this->option('force'),
        ]);
    }

    protected function publishMigrations(): void
    {
        $migrationPath = database_path('migrations');
        $existing = collect(File::glob($migrationPath . DIRECTORY_SEPARATOR . '*_create_oneauth_*_table.php'));

        if ($existing->isNotEmpty() && !$this->option('force')) {
            $this->line('OneAuth migrations already published (skipped)');

            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'oneauth-migrations',
            '--force' => (bool) $this->option('force'),
        ]);
    }

    protected function reportTableStatus(): void
    {
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
            $this->warn('Some OneAuth tables are missing. Run: php artisan migrate');
            $this->warn('Migrations are safe to re-run. Existing tables are skipped.');
        }
    }
}
