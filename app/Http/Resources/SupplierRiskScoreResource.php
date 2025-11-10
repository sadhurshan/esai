<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupplierRiskScore */
class SupplierRiskScoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'supplier_id' => $this->supplier_id,
            'on_time_delivery_rate' => $this->on_time_delivery_rate !== null ? (float) $this->on_time_delivery_rate : null,
            'defect_rate' => $this->defect_rate !== null ? (float) $this->defect_rate : null,
            'price_volatility' => $this->price_volatility !== null ? (float) $this->price_volatility : null,
            'lead_time_volatility' => $this->lead_time_volatility !== null ? (float) $this->lead_time_volatility : null,
            'responsiveness_rate' => $this->responsiveness_rate !== null ? (float) $this->responsiveness_rate : null,
            'overall_score' => $this->overall_score !== null ? (float) $this->overall_score : null,
            'risk_grade' => $this->risk_grade?->value,
            'badges' => $this->badges_json ?? [],
            'meta' => $this->meta ?? [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
