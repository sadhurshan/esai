<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RFQQuote */
class RFQQuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => optional($this->supplier)->name,
            'unit_price_usd' => $this->unit_price_usd,
            'total_price_usd' => $this->whenLoaded('rfq', function (): float {
                $quantity = (int) ($this->rfq?->quantity ?? 0);

                return (float) $this->unit_price_usd * max($quantity, 1);
            }),
            'revision' => 1,
            'lead_time_days' => $this->lead_time_days,
            'note' => $this->note,
            'attachment_path' => $this->attachment_path,
            'via' => $this->via,
            'status' => 'submitted',
            'submitted_at' => optional($this->submitted_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
