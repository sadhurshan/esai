<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrderEvent */
class PurchaseOrderEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'purchase_order_id' => $this->purchase_order_id,
            'type' => $this->event_type,
            'summary' => $this->summary,
            'description' => $this->description,
            'metadata' => $this->meta,
            'actor' => $this->resolveActor(),
            'occurred_at' => optional($this->occurred_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }

    private function resolveActor(): ?array
    {
        if ($this->relationLoaded('actor') && $this->actor) {
            return [
                'id' => $this->actor->getKey(),
                'name' => $this->actor->name,
                'email' => $this->actor->email,
                'type' => $this->actor_type ?? 'user',
            ];
        }

        if ($this->actor_id || $this->actor_name) {
            return [
                'id' => $this->actor_id,
                'name' => $this->actor_name,
                'type' => $this->actor_type,
            ];
        }

        return null;
    }
}
