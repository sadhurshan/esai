<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin Order */
class SalesOrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $buyerCompany = $order->relationLoaded('company') ? $order->getRelation('company') : null;
        $supplierCompany = $order->relationLoaded('supplierCompany') ? $order->getRelation('supplierCompany') : null;

        $orderedQty = (float) ($order->ordered_qty ?? 0);
        $shippedQty = (float) ($order->shipped_qty ?? 0);
        $fulfillmentPercent = $orderedQty > 0
            ? min(100, round(($shippedQty / $orderedQty) * 100, 2))
            : 0.0;

        return [
            'id' => $order->getKey(),
            'soNumber' => $order->so_number,
            'poId' => $order->purchase_order_id,
            'buyerCompanyId' => $order->company_id,
            'buyerCompanyName' => $buyerCompany?->name,
            'supplierCompanyId' => $order->supplier_company_id,
            'supplierCompanyName' => $supplierCompany?->name,
            'status' => $this->mapUiStatus($order),
            'currency' => $order->currency,
            'totals' => [
                'currency' => $order->currency,
                'subtotalMinor' => Arr::get($order->metadata, 'financials.subtotal_minor'),
                'taxMinor' => Arr::get($order->metadata, 'financials.tax_minor'),
                'totalMinor' => $order->total_minor,
            ],
            'issueDate' => optional($order->ordered_at)?->toIso8601String(),
            'dueDate' => Arr::get($order->metadata, 'expected_at'),
            'notes' => Arr::get($order->metadata, 'notes'),
            'shipping' => $order->shipping,
            'fulfillment' => [
                'orderedQty' => $orderedQty,
                'shippedQty' => $shippedQty,
                'percent' => $fulfillmentPercent,
                'updatedAt' => optional($order->updated_at)?->toIso8601String(),
            ],
            'shipmentsCount' => (int) Arr::get($order->metadata, 'shipments_count', 0),
            'lastEventAt' => Arr::get($order->metadata, 'last_event_at') ?? optional($order->updated_at)?->toIso8601String(),
        ];
    }

    private function mapUiStatus(Order $order): string
    {
        $poStatus = Arr::get($order->metadata, 'po_status');

        return match ($order->status) {
            'pending' => match ($poStatus) {
                'sent' => 'pending_ack',
                default => 'draft',
            },
            'in_production' => 'accepted',
            'in_transit' => 'partially_fulfilled',
            'delivered' => 'fulfilled',
            'cancelled' => 'cancelled',
            default => 'draft',
        };
    }
}
