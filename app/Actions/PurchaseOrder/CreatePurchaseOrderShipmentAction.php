<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\PurchaseOrderShipmentLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrderShipmentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordPurchaseOrderEventAction $recordEvent,
    ) {}

    /**
     * @param array<int, array{so_line_id:int, qty_shipped:float}> $linePayloads
     */
    public function execute(PurchaseOrder $order, User $actor, array $attributes, array $linePayloads): PurchaseOrderShipment
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Cannot create shipments for cancelled orders.'],
            ]);
        }

        $order->loadMissing('lines');

        $supplierCompanyId = $this->resolveSupplierCompanyId($order);

        return DB::transaction(function () use ($order, $actor, $attributes, $linePayloads, $supplierCompanyId): PurchaseOrderShipment {
            $shipmentNumber = $this->generateShipmentNumber($order);
            $shippedAt = Carbon::parse(Arr::get($attributes, 'shipped_at'));

            $shipment = PurchaseOrderShipment::query()->create([
                'company_id' => $order->company_id,
                'supplier_company_id' => $supplierCompanyId,
                'purchase_order_id' => $order->getKey(),
                'shipment_number' => $shipmentNumber,
                'status' => 'pending',
                'carrier' => Arr::get($attributes, 'carrier'),
                'tracking_number' => Arr::get($attributes, 'tracking_number'),
                'shipped_at' => $shippedAt,
                'notes' => Arr::get($attributes, 'notes'),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $lineMap = $this->resolveLines($order, $linePayloads);

            foreach ($lineMap as $lineId => $qty) {
                PurchaseOrderShipmentLine::query()->create([
                    'purchase_order_shipment_id' => $shipment->getKey(),
                    'purchase_order_line_id' => $lineId,
                    'qty_shipped' => $qty,
                ]);
            }

            $this->auditLogger->created($shipment);

            $this->recordEvent->execute(
                $order,
                'shipment_created',
                sprintf('Shipment %s created', $shipment->shipment_number),
                null,
                [
                    'shipment_id' => $shipment->getKey(),
                    'carrier' => $shipment->carrier,
                    'tracking_number' => $shipment->tracking_number,
                    'lines' => $lineMap,
                ],
                $actor,
                $shipment->shipped_at ?? now(),
            );

            return $shipment->load(['lines.purchaseOrderLine']);
        });
    }

    private function resolveLines(PurchaseOrder $order, array $linePayloads): array
    {
        $lines = $order->getRelation('lines')->keyBy(fn (PurchaseOrderLine $line) => $line->getKey());
        $grouped = [];

        foreach ($linePayloads as $payload) {
            $lineId = (int) ($payload['so_line_id'] ?? 0);
            $qty = (float) ($payload['qty_shipped'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $grouped[$lineId] = ($grouped[$lineId] ?? 0) + $qty;
        }

        if (empty($grouped)) {
            throw ValidationException::withMessages([
                'lines' => ['At least one line with quantity is required.'],
            ]);
        }

        $lineIds = array_keys($grouped);

        foreach ($lineIds as $lineId) {
            if (! $lines->has($lineId)) {
                throw ValidationException::withMessages([
                    'lines' => ['Lines must belong to the purchase order.'],
                ]);
            }
        }

        $existing = PurchaseOrderShipmentLine::query()
            ->select('purchase_order_line_id', DB::raw('SUM(qty_shipped) as total'))
            ->whereIn('purchase_order_line_id', $lineIds)
            ->whereHas('shipment', function ($query) use ($order): void {
                $query
                    ->where('purchase_order_id', $order->getKey())
                    ->where('status', '!=', 'cancelled')
                    ->whereNull('purchase_order_shipments.deleted_at');
            })
            ->groupBy('purchase_order_line_id')
            ->pluck('total', 'purchase_order_line_id');

        foreach ($grouped as $lineId => $qty) {
            /** @var PurchaseOrderLine $line */
            $line = $lines->get($lineId);
            $alreadyShipped = (float) ($existing[$lineId] ?? 0);
            $remaining = (float) $line->quantity - $alreadyShipped;

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'lines' => [sprintf('Line %d is fully shipped.', $line->line_no)],
                ]);
            }

            if ($qty > $remaining) {
                throw ValidationException::withMessages([
                    'lines' => [sprintf('Cannot ship %.2f units for line %d. Only %.2f remaining.', $qty, $line->line_no, $remaining)],
                ]);
            }
        }

        return $grouped;
    }

    private function resolveSupplierCompanyId(PurchaseOrder $order): int
    {
        $supplier = $order->supplier ?? $order->quote?->supplier;

        $companyId = $supplier?->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Supplier context missing for shipment creation.'],
            ]);
        }

        return (int) $companyId;
    }

    private function generateShipmentNumber(PurchaseOrder $order): string
    {
        return sprintf('%s-SHP-%s',
            $order->po_number,
            Str::upper(Str::random(4))
        );
    }
}
