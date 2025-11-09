<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InvoiceMatch */
class InvoiceMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'purchase_order_id' => $this->purchase_order_id,
            'goods_receipt_note_id' => $this->goods_receipt_note_id,
            'result' => $this->result,
            'details' => $this->details,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
