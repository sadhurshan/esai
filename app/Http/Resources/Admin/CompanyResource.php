<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Company */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value,
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'notes' => $this->notes,
            'plan' => $this->whenLoaded('plan', fn () => PlanResource::make($this->plan)),
            'supplier_status' => $this->supplier_status?->value,
            'owner_user_id' => $this->owner_user_id,
            'rfqs_monthly_used' => $this->rfqs_monthly_used,
            'storage_used_mb' => $this->storage_used_mb,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
