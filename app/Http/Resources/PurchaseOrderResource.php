<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'po_number' => $this->po_number,
            'status' => $this->status,
            'currency' => $this->currency,
            'incoterm' => $this->incoterm,
            'tax_percent' => $this->tax_percent,
            'revision_no' => $this->revision_no,
            'rfq_id' => $this->rfq_id,
            'quote_id' => $this->quote_id,
            'supplier' => $this->when(
                $this->relationLoaded('supplier') || $this->relationLoaded('quote'),
                function () {
                    if ($this->supplier) {
                        return [
                            'id' => $this->supplier->id,
                            'name' => $this->supplier->name,
                        ];
                    }

                    if ($this->quote?->supplier) {
                        return [
                            'id' => $this->quote->supplier->id,
                            'name' => $this->quote->supplier->name,
                        ];
                    }

                    if ($this->quote?->supplier_id) {
                        return [
                            'id' => $this->quote->supplier_id,
                            'name' => null,
                        ];
                    }

                    return null;
                }
            ),
            'rfq' => $this->whenLoaded('rfq', fn () => [
                'id' => $this->rfq?->id,
                'number' => $this->rfq?->number,
                'title' => $this->rfq?->title,
            ]),
            'lines' => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'change_orders' => PoChangeOrderResource::collection($this->whenLoaded('changeOrders')),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
