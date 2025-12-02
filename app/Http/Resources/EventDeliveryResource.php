<?php

namespace App\Http\Resources;

use App\Enums\WebhookDeliveryStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WebhookDelivery */
class EventDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof WebhookDeliveryStatus
            ? $this->status->value
            : $this->status;

        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'endpoint' => $this->subscription?->url,
            'event' => $this->event,
            'status' => $status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'latency_ms' => $this->latency_ms,
            'response_code' => $this->response_code,
            'response_body' => $this->response_body,
            'last_error' => $this->last_error,
            'payload' => $this->payload,
            'dead_lettered_at' => $this->dead_lettered_at?->toISOString(),
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
