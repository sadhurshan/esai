<?php

namespace App\Http\Resources;

use App\Models\AnalyticsSnapshot;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnalyticsSnapshot */
class AnalyticsSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'type' => $this->type,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'value' => (float) $this->value,
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
