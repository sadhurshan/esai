<?php

namespace App\Http\Resources;

use App\Http\Resources\DocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RfpProposal */
class RfpProposalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfp_id' => $this->rfp_id,
            'company_id' => $this->company_id,
            'supplier_company_id' => $this->supplier_company_id,
            'status' => $this->status,
            'price_total' => $this->price_total,
            'price_total_minor' => $this->price_total_minor,
            'currency' => $this->currency,
            'lead_time_days' => $this->lead_time_days,
            'approach_summary' => $this->approach_summary,
            'schedule_summary' => $this->schedule_summary,
            'value_add_summary' => $this->value_add_summary,
            'attachments_count' => $this->attachments_count,
            'meta' => $this->meta ?? [],
            'supplier_company' => $this->whenLoaded('supplierCompany', function () use ($request) {
                return [
                    'id' => $this->supplierCompany?->id,
                    'name' => $this->supplierCompany?->name,
                ];
            }),
            'attachments' => $this->relationLoaded('documents')
                ? DocumentResource::collection($this->documents)->toArray($request)
                : [],
            'submitted_by' => $this->submitted_by,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
