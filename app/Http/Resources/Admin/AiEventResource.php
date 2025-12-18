<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AiEvent */
class AiEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'timestamp' => $this->created_at?->toISOString(),
            'feature' => $this->feature,
            'status' => $this->status,
            'latency_ms' => $this->latency_ms,
            'error_message' => $this->error_message,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                ];
            }),
            'entity' => $this->entityReference(),
        ];
    }

    private function entityReference(): ?array
    {
        if (! $this->entity_type) {
            return null;
        }

        return [
            'type' => $this->entity_type,
            'id' => $this->entity_id ? (string) $this->entity_id : null,
            'label' => $this->entityLabel(),
        ];
    }

    private function entityLabel(): string
    {
        $type = $this->entity_type ? class_basename($this->entity_type) : 'Entity';

        return sprintf('%s #%s', $type, $this->entity_id ?? '—');
    }
}
