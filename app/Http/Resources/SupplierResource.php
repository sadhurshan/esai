<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Supplier */
class SupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'capabilities' => $this->capabilities,
            'status' => $this->status,
            'rating_avg' => $this->rating_avg,
            'risk_grade' => $this->risk_grade?->value,
            'contact' => [
                'email' => $this->email,
                'phone' => $this->phone,
                'website' => $this->website,
            ],
            'address' => [
                'line1' => $this->address,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'geo' => [
                'lat' => $this->geo_lat,
                'lng' => $this->geo_lng,
            ],
            'lead_time_days' => $this->lead_time_days,
            'moq' => $this->moq,
            'verified_at' => optional($this->verified_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
