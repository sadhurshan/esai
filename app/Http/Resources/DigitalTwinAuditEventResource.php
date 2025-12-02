<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DigitalTwinAuditEvent */
class DigitalTwinAuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event?->value ?? $this->event,
            'meta' => $this->meta,
            'actor' => $this->whenLoaded('actor', function () {
                return [
                    'id' => $this->actor?->id,
                    'name' => $this->actor?->name,
                    'email' => $this->actor?->email,
                ];
            }),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
