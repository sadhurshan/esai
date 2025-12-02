<?php

namespace App\Services;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DispatchWebhookJob;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;

class EventDeliveryService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function retryDelivery(WebhookDelivery $delivery, User $user): void
    {
        DispatchWebhookJob::dispatch(
            $delivery->subscription_id,
            $delivery->event,
            $delivery->payload ?? [],
            $delivery->id
        );

        $delivery->forceFill([
            'attempts' => 0,
            'status' => WebhookDeliveryStatus::Pending,
            'dead_lettered_at' => null,
            'last_error' => null,
            'dispatched_at' => null,
            'delivered_at' => null,
            'latency_ms' => null,
            'response_code' => null,
            'response_body' => null,
        ])->save();

        $this->auditLogger->custom($delivery, 'webhook_delivery.retry', [
            'initiator_id' => $user->id,
        ]);
    }

    /**
     * @param  Collection<int, WebhookDelivery>  $deliveries
     */
    public function replayDeadLetters(Collection $deliveries, User $user): int
    {
        $count = 0;

        $deliveries->each(function (WebhookDelivery $delivery) use ($user, &$count): void {
            DispatchWebhookJob::dispatch(
                $delivery->subscription_id,
                $delivery->event,
                $delivery->payload ?? [],
                $delivery->id
            );

            $delivery->forceFill([
                'attempts' => 0,
                'status' => WebhookDeliveryStatus::Pending,
                'dead_lettered_at' => null,
                'last_error' => null,
                'dispatched_at' => null,
                'delivered_at' => null,
                'latency_ms' => null,
                'response_code' => null,
                'response_body' => null,
            ])->save();

            $this->auditLogger->custom($delivery, 'webhook_delivery.replay', [
                'initiator_id' => $user->id,
            ]);

            $count++;
        });

        return $count;
    }
}
