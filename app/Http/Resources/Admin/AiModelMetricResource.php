<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AiModelMetric */
class AiModelMetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'company_id' => $this->company_id,
            'feature' => $this->feature,
            'metric_name' => $this->metric_name,
            'metric_value' => $this->metric_value !== null ? (float) $this->metric_value : null,
            'window_start' => $this->window_start?->toISOString(),
            'window_end' => $this->window_end?->toISOString(),
            'notes' => $this->notes,
            'entity' => $this->entity_type ? [
                'type' => $this->entity_type,
                'id' => $this->entity_id ? (string) $this->entity_id : null,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
