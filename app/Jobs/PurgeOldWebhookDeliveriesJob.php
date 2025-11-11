<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeOldWebhookDeliveriesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $threshold = now()->subDays(30);

        WebhookDelivery::query()
            ->where('created_at', '<', $threshold)
            ->limit(500)
            ->each(function (WebhookDelivery $delivery): void {
                $delivery->delete();
            });
    }
}
