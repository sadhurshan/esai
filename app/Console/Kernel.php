<?php

namespace App\Console;

use App\Jobs\AuditSupplierDocumentExpiryJob;
use App\Jobs\ComputeInventoryForecastSnapshotsJob;
use App\Jobs\ComputeTenantUsageJob;
use App\Jobs\PurgeExpiredExportsJob;
use App\Jobs\RetryFailedWebhooksJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new AuditSupplierDocumentExpiryJob())
            ->dailyAt('01:15')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new PurgeExpiredExportsJob())
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new ComputeTenantUsageJob())
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new ComputeInventoryForecastSnapshotsJob())
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->job(new RetryFailedWebhooksJob())
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
