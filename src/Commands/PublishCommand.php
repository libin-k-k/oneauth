<?php

namespace Libinkk\OneAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishCommand extends Command
{
    protected $signature = 'oneauth:publish {--force : Overwrite published files if they already exist}';

    protected $description = 'Publish OneAuth assets safely';

    public function handle(): int
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->info('OneAuth publish completed.');

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
        $existing = collect(File::glob(database_path('migrations') . DIRECTORY_SEPARATOR . '*_create_oneauth_*_table.php'));

        if ($existing->isNotEmpty() && !$this->option('force')) {
            $this->line('OneAuth migrations already published (skipped)');

            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'oneauth-migrations',
            '--force' => (bool) $this->option('force'),
        ]);
    }
}
