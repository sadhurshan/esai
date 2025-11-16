<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'event' => $this->action,
            'timestamp' => $this->created_at?->toISOString(),
            'actor' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
            'resource' => [
                'type' => $this->entity_type,
                'id' => (string) $this->entity_id,
                'label' => $this->resourceLabel(),
            ],
            'metadata' => [
                'before' => $this->before,
                'after' => $this->after,
            ],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
        ];
    }

    private function resourceLabel(): string
    {
        $type = $this->entity_type ? class_basename($this->entity_type) : 'Resource';

        return sprintf('%s #%s', $type, $this->entity_id ?? '—');
    }
}
