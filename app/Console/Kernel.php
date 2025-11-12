<?php

namespace App\Console;

use App\Jobs\PurgeExpiredExportsJob;
use App\Jobs\RetryFailedWebhooksJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new PurgeExpiredExportsJob())
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new RetryFailedWebhooksJob())
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // TODO: clarify with spec whether an inventory forecast snapshot job should run on a schedule once implemented.
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
