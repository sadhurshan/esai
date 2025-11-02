<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DemoReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:demo-reset {--force : Run the reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the demo database, seed sample data, and ensure storage links exist.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will reset the database and seed demo data. Continue?', true)) {
            $this->info('Demo reset cancelled.');

            return self::SUCCESS;
        }

        $this->info('Refreshing database...');
        $this->call('migrate:fresh', ['--seed' => true]);

        $this->info('Ensuring storage symlink exists...');
        if (! is_link(public_path('storage')) && ! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        } else {
            $this->line('Public storage link already present.');
        }

        $this->newLine();
        $this->info('Demo environment ready. Consider running `composer run dev` to boot local services.');

        return self::SUCCESS;
    }
}
