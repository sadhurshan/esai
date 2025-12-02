<?php

namespace App\Http\Resources;

use App\Models\Currency;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;

/** @mixin Order */
class SalesOrderDetailResource extends SalesOrderSummaryResource
{
    private static array $minorUnitCache = [];

    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);
        /** @var Order $order */
        $order = $this->resource;
        $purchaseOrder = $order->relationLoaded('purchaseOrder')
            ? $order->getRelation('purchaseOrder')
            : null;

        /** @var EloquentCollection<int, PurchaseOrderLine>|null $lines */
        $lines = $purchaseOrder && $purchaseOrder->relationLoaded('lines')
            ? $purchaseOrder->getRelation('lines')
            : null;

        $base['lines'] = $lines
            ? $lines->map(fn ($line) => $this->transformLine($line, $order->currency ?? 'USD'))->values()->all()
            : [];
        $base['shipments'] = $purchaseOrder ? $this->transformShipments($purchaseOrder, $order) : [];
        $base['timeline'] = $this->buildTimeline($order, $purchaseOrder);
        $base['acknowledgements'] = $this->buildAcknowledgements($purchaseOrder);

        return $base;
    }

    private function transformLine(PurchaseOrderLine $line, string $currency): array
    {
        $minorUnit = $this->minorUnitFor($currency);

        return [
            'id' => $line->getKey(),
            'soLineId' => $line->getKey(),
            'poLineId' => $line->getKey(),
            'itemId' => $line->rfq_item_id,
            'sku' => null,
            'description' => $line->description,
            'uom' => $line->uom,
            'qtyOrdered' => (float) $line->quantity,
            'qtyAllocated' => null,
            'qtyShipped' => $this->resolveLineShippedQuantity($line),
            'unitPriceMinor' => $this->decimalToMinor($line->unit_price, $minorUnit),
            'currency' => $currency,
        ];
    }

    private function transformShipments(PurchaseOrder $purchaseOrder, Order $order): array
    {
        if (! $purchaseOrder->relationLoaded('shipments')) {
            return [];
        }

        return $purchaseOrder->getRelation('shipments')
            ->reject(fn ($shipment) => $shipment->status === 'cancelled')
            ->map(function ($shipment) use ($order): array {
                /** @var \App\Models\PurchaseOrderShipment $shipment */
                $lines = $shipment->relationLoaded('lines') ? $shipment->getRelation('lines') : collect();

                return [
                    'id' => $shipment->getKey(),
                    'soId' => $order->getKey(),
                    'shipmentNo' => $shipment->shipment_number,
                    'status' => $shipment->status,
                    'carrier' => $shipment->carrier,
                    'trackingNumber' => $shipment->tracking_number,
                    'shippedAt' => optional($shipment->shipped_at)?->toIso8601String(),
                    'deliveredAt' => optional($shipment->delivered_at)?->toIso8601String(),
                    'notes' => $shipment->notes,
                    'lines' => $lines
                        ->map(fn ($line) => [
                            'soLineId' => $line->purchase_order_line_id,
                            'qtyShipped' => (float) $line->qty_shipped,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveLineShippedQuantity(PurchaseOrderLine $line): float
    {
        if (! $line->relationLoaded('shipmentLines')) {
            return 0.0;
        }

        return $line->getRelation('shipmentLines')
            ->reduce(function (float $carry, $shipmentLine): float {
                $shipment = $shipmentLine->relationLoaded('shipment')
                    ? $shipmentLine->getRelation('shipment')
                    : null;

                if ($shipment && $shipment->status === 'cancelled') {
                    return $carry;
                }

                return $carry + (float) $shipmentLine->qty_shipped;
            }, 0.0);
    }

    private function buildTimeline(Order $order, ?PurchaseOrder $purchaseOrder): array
    {
        $projectedTimeline = $order->timeline ?? [];

        if (! empty($projectedTimeline)) {
            return collect($projectedTimeline)
                ->map(function ($entry, int $index): array {
                    return [
                        'id' => $entry['id'] ?? $index,
                        'type' => $this->mapTimelineType($entry['type'] ?? null),
                        'summary' => $entry['summary'] ?? '',
                        'description' => $entry['description'] ?? null,
                        'occurredAt' => $entry['occurred_at'] ?? $entry['occurredAt'] ?? null,
                        'actor' => $entry['actor'] ?? null,
                        'metadata' => $entry['meta'] ?? $entry['metadata'] ?? null,
                    ];
                })
                ->values()
                ->all();
        }

        if ($purchaseOrder === null) {
            return [];
        }

        /** @var EloquentCollection<int, PurchaseOrderEvent>|null $events */
        $events = $purchaseOrder->relationLoaded('events') ? $purchaseOrder->getRelation('events') : null;

        if (! $events) {
            return [];
        }

        return $events
            ->map(function (PurchaseOrderEvent $event): array {
                return [
                    'id' => $event->getKey(),
                    'type' => $this->mapTimelineType($event->event_type),
                    'summary' => $event->summary,
                    'description' => $event->description,
                    'occurredAt' => optional($event->occurred_at)?->toIso8601String(),
                    'actor' => $event->actor_id ? [
                        'id' => $event->actor_id,
                        'name' => $event->actor_name,
                        'email' => null,
                    ] : null,
                    'metadata' => $event->meta ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function buildAcknowledgements(?PurchaseOrder $order): array
    {
        if ($order === null) {
            return [];
        }

        if (! in_array($order->ack_status, ['acknowledged', 'declined'], true)) {
            return [];
        }

        return [[
            'decision' => $order->ack_status === 'declined' ? 'decline' : 'accept',
            'reason' => $order->ack_reason,
            'acknowledgedAt' => optional($order->acknowledged_at)?->toIso8601String(),
            'actorName' => $order->quote?->supplier?->name ?? $order->supplier?->name,
        ]];
    }

    private function mapTimelineType(?string $eventType): string
    {
        return match ($eventType) {
            'supplier_ack', 'supplier_decline' => 'acknowledged',
            'shipment_created', 'shipment_status' => 'shipment',
            'sent' => 'status_change',
            default => 'note',
        };
    }

    private function minorUnitFor(string $currency): int
    {
        if (! array_key_exists($currency, self::$minorUnitCache)) {
            $record = Currency::query()->where('code', strtoupper($currency))->first();
            self::$minorUnitCache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) self::$minorUnitCache[$currency];
    }

    private function decimalToMinor(mixed $value, int $minorUnit): int
    {
        if ($value === null) {
            return 0;
        }

        return (int) round(((float) $value) * (10 ** $minorUnit));
    }
}
