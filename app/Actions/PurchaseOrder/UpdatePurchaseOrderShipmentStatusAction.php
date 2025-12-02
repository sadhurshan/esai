<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrderShipment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseOrderShipmentStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordPurchaseOrderEventAction $recordEvent,
    ) {}

    public function execute(PurchaseOrderShipment $shipment, User $actor, string $status, ?string $deliveredAt = null): PurchaseOrderShipment
    {
        $shipment->loadMissing('purchaseOrder');

        if ($shipment->purchaseOrder === null) {
            throw ValidationException::withMessages([
                'shipment_id' => ['Shipment is not linked to a purchase order.'],
            ]);
        }

        if ($shipment->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['Cannot update a cancelled shipment.'],
            ]);
        }

        if ($shipment->status === 'delivered' && $status === 'delivered') {
            throw ValidationException::withMessages([
                'status' => ['Shipment already delivered.'],
            ]);
        }

        if (! $this->isTransitionAllowed($shipment->status, $status)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid shipment status transition.'],
            ]);
        }

        return DB::transaction(function () use ($shipment, $actor, $status, $deliveredAt): PurchaseOrderShipment {
            $before = $shipment->getOriginal();

            $shipment->status = $status;
            $shipment->updated_by = $actor->getKey();

            if ($status === 'delivered') {
                $shipment->delivered_at = $deliveredAt ? Carbon::parse($deliveredAt) : now();
            }

            $shipment->save();

            $this->auditLogger->updated($shipment, $before, $shipment->getChanges());

            $this->recordEvent->execute(
                $shipment->purchaseOrder,
                'shipment_status',
                sprintf('Shipment %s marked %s', $shipment->shipment_number, $status),
                null,
                [
                    'shipment_id' => $shipment->getKey(),
                    'status' => $status,
                    'delivered_at' => optional($shipment->delivered_at)?->toIso8601String(),
                ],
                $actor,
                $status === 'delivered' ? $shipment->delivered_at : now(),
            );

            return $shipment->load(['lines.purchaseOrderLine', 'purchaseOrder']);
        });
    }

    private function isTransitionAllowed(string $current, string $target): bool
    {
        if ($current === $target) {
            return true;
        }

        return match ($current) {
            'pending' => in_array($target, ['in_transit', 'delivered'], true),
            'in_transit' => $target === 'delivered',
            'delivered' => false,
            default => false,
        };
    }
}
