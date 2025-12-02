<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFailedWebhooksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Failed)
            ->whereNull('dead_lettered_at')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->chunkById(100, function ($deliveries): void {
                foreach ($deliveries as $delivery) {
                    $subscription = $delivery->subscription;

                    if ($subscription === null) {
                        continue;
                    }

                    $policy = $subscription->retry_policy_json ?? ['max' => 5];
                    $maxAttempts = (int) ($delivery->max_attempts ?: $policy['max'] ?? 5);

                    if ($delivery->attempts >= $maxAttempts) {
                        $delivery->forceFill([
                            'status' => WebhookDeliveryStatus::DeadLettered,
                            'dead_lettered_at' => now(),
                        ])->save();

                        continue;
                    }

                    DispatchWebhookJob::dispatch($delivery->subscription_id, $delivery->event, $delivery->payload ?? [], $delivery->id);
                }
            });
    }
}
