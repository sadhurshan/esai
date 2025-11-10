<?php

namespace App\Http\Resources;

use App\Models\ApprovalRule;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApprovalRule */
class ApprovalRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => (int) $this->company_id,
            'target_type' => $this->target_type,
            'threshold_min' => (float) $this->threshold_min,
            'threshold_max' => $this->threshold_max !== null ? (float) $this->threshold_max : null,
            'levels' => $this->orderedLevels(),
            'active' => (bool) $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
