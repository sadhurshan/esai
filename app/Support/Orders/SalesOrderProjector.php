<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Models\PurchaseOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SalesOrderProjector
{
    public function sync(PurchaseOrder $purchaseOrder): Order
    {
        $purchaseOrder->loadMissing([
            'company',
            'supplier',
            'quote.supplier',
            'lines.shipmentLines.shipment',
            'shipments',
            'events',
        ]);

        $supplierCompanyId = $purchaseOrder->supplier?->company_id
            ?? $purchaseOrder->quote?->supplier?->company_id;

        $orderedQty = (int) ($purchaseOrder->lines?->sum('quantity') ?? 0);
        $shippedQty = $this->calculateShippedQuantity($purchaseOrder);
        $status = $this->mapStatus($purchaseOrder, $orderedQty, $shippedQty);
        $timeline = $this->buildTimeline($purchaseOrder);
        $shipmentsCount = $purchaseOrder->relationLoaded('shipments')
            ? $purchaseOrder->shipments->where('status', '!=', 'cancelled')->count()
            : $purchaseOrder->shipments()->where('status', '!=', 'cancelled')->count();
        $latestEventAt = $purchaseOrder->relationLoaded('events')
            ? optional($purchaseOrder->events->first()?->occurred_at)?->toIso8601String()
            : null;

        $payload = [
            'purchase_order_id' => $purchaseOrder->getKey(),
            'company_id' => (int) $purchaseOrder->company_id,
            'supplier_company_id' => $supplierCompanyId,
            'number' => $purchaseOrder->po_number ?? sprintf('SO-%05d', $purchaseOrder->getKey() ?? 0),
            'so_number' => $purchaseOrder->po_number,
            'status' => $status,
            'currency' => $purchaseOrder->currency ?? 'USD',
            'total_minor' => (int) ($purchaseOrder->total_minor ?? 0),
            'ordered_qty' => $orderedQty,
            'shipped_qty' => $shippedQty,
            'timeline' => $timeline,
            'shipping' => $this->resolveShippingProfile($purchaseOrder),
            'metadata' => array_filter([
                'po_status' => $purchaseOrder->status,
                'ack_status' => $purchaseOrder->ack_status,
                'rfq_id' => $purchaseOrder->rfq_id,
                'quote_id' => $purchaseOrder->quote_id,
                'expected_at' => optional($purchaseOrder->expected_at)?->toIso8601String(),
                'shipments_count' => $shipmentsCount,
                'last_event_at' => $latestEventAt,
                'financials' => array_filter([
                    'subtotal_minor' => $purchaseOrder->subtotal_minor,
                    'tax_minor' => $purchaseOrder->tax_amount_minor,
                ], static fn ($value) => $value !== null),
            ], static fn ($value) => $value !== null && $value !== []),
            'ordered_at' => $purchaseOrder->sent_at ?? $purchaseOrder->created_at ?? Carbon::now(),
            'acknowledged_at' => $purchaseOrder->acknowledged_at,
            'delivered_at' => $this->resolveDeliveredAt($purchaseOrder, $status),
        ];

        $order = Order::withTrashed()->firstOrNew(['purchase_order_id' => $purchaseOrder->getKey()]);
        $order->fill($payload);

        if ($order->trashed()) {
            $order->restore();
        }

        $order->save();

        return $order->fresh();
    }

    public function deleteFor(PurchaseOrder $purchaseOrder): void
    {
        Order::query()
            ->where('purchase_order_id', $purchaseOrder->getKey())
            ->get()
            ->each(fn (Order $order) => $order->delete());
    }

    private function calculateShippedQuantity(PurchaseOrder $purchaseOrder): int
    {
        if (! $purchaseOrder->relationLoaded('lines')) {
            $purchaseOrder->loadMissing('lines.shipmentLines.shipment');
        }

        return (int) $purchaseOrder->lines
            ->reduce(function (int $carry, $line): int {
                $lineQty = $line->shipmentLines
                    ->filter(fn ($shipmentLine) => $shipmentLine->relationLoaded('shipment')
                        ? $shipmentLine->shipment?->status !== 'cancelled'
                        : true)
                    ->reduce(fn (int $subtotal, $shipmentLine): int => $subtotal + (int) $shipmentLine->qty_shipped, 0);

                return $carry + $lineQty;
            }, 0);
    }

    private function mapStatus(PurchaseOrder $purchaseOrder, int $orderedQty, int $shippedQty): string
    {
        if ($purchaseOrder->status === 'cancelled') {
            return 'cancelled';
        }

        if ($orderedQty > 0 && $shippedQty >= $orderedQty) {
            return 'delivered';
        }

        if ($shippedQty > 0) {
            return 'in_transit';
        }

        return match ($purchaseOrder->status) {
            'acknowledged', 'confirmed' => 'in_production',
            default => 'pending',
        };
    }

    private function buildTimeline(PurchaseOrder $purchaseOrder): array
    {
        $events = $purchaseOrder->relationLoaded('events')
            ? $purchaseOrder->getRelation('events')
            : collect();

        return $events
            ->take(25)
            ->map(function ($event): array {
                return array_filter([
                    'id' => $event->getKey(),
                    'type' => $event->event_type,
                    'summary' => $event->summary,
                    'description' => $event->description,
                    'occurred_at' => optional($event->occurred_at)->toIso8601String(),
                    'actor' => array_filter([
                        'id' => $event->actor_id,
                        'name' => $event->actor_name,
                        'type' => $event->actor_type,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'meta' => $event->meta,
                ], fn ($value) => $value !== null && $value !== []);
            })
            ->values()
            ->all();
    }

    private function resolveShippingProfile(PurchaseOrder $purchaseOrder): ?array
    {
        $shipTo = Arr::get($purchaseOrder->toArray(), 'ship_to');
        $incoterm = Arr::get($purchaseOrder->toArray(), 'incoterm');

        if ($shipTo === null && $incoterm === null) {
            return null;
        }

        return array_filter([
            'ship_to' => $shipTo,
            'incoterm' => $incoterm,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function resolveDeliveredAt(PurchaseOrder $purchaseOrder, string $status): ?Carbon
    {
        if ($status !== 'delivered') {
            return $purchaseOrder->delivered_at;
        }

        return $purchaseOrder->delivered_at ?? Carbon::now();
    }
}
