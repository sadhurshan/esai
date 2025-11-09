<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InvoiceLine */
class InvoiceLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quantity = (int) $this->quantity;
        $unitPrice = (float) $this->unit_price;

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'po_line_id' => $this->po_line_id,
            'description' => $this->description,
            'quantity' => $quantity,
            'uom' => $this->uom,
            'unit_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
        ];
    }
}
