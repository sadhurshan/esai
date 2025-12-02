<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrderShipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderShipment */
class PurchaseOrderShipmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'shipmentNo' => $this->shipment_number,
            'purchaseOrderId' => $this->purchase_order_id,
            'status' => $this->status,
            'carrier' => $this->carrier,
            'trackingNumber' => $this->tracking_number,
            'shippedAt' => optional($this->shipped_at)?->toIso8601String(),
            'deliveredAt' => optional($this->delivered_at)?->toIso8601String(),
            'notes' => $this->notes,
            'lines' => $this->transformLines(),
        ];
    }

    private function transformLines(): array
    {
        if (! $this->relationLoaded('lines')) {
            return [];
        }

        return $this->getRelation('lines')
            ->map(static function ($line): array {
                return [
                    'soLineId' => $line->purchase_order_line_id,
                    'poLineId' => $line->purchase_order_line_id,
                    'lineNumber' => $line->purchaseOrderLine?->line_no,
                    'qtyShipped' => (float) $line->qty_shipped,
                ];
            })
            ->values()
            ->all();
    }
}
