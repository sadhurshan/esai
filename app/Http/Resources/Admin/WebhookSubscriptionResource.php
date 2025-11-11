<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WebhookSubscription */
class WebhookSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'url' => $this->url,
            'secret_hint' => $this->secret !== null ? substr($this->secret, -4) : null,
            'events' => $this->events,
            'active' => $this->active,
            'retry_policy' => $this->retry_policy_json,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
