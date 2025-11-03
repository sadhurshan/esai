<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QuoteItem */
class QuoteItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_item_id' => $this->rfq_item_id,
            'unit_price' => (float) $this->unit_price,
            'lead_time_days' => $this->lead_time_days,
            'note' => $this->note,
        ];
    }
}
