<?php

namespace App\Observers;

use App\Models\PurchaseOrderShipment;
use App\Support\Orders\SalesOrderProjector;

class PurchaseOrderShipmentObserver
{
    public function __construct(private readonly SalesOrderProjector $projector)
    {
    }

    public function saved(PurchaseOrderShipment $shipment): void
    {
        $this->reproject($shipment);
    }

    public function deleted(PurchaseOrderShipment $shipment): void
    {
        $this->reproject($shipment);
    }

    public function restored(PurchaseOrderShipment $shipment): void
    {
        $this->reproject($shipment);
    }

    public function forceDeleted(PurchaseOrderShipment $shipment): void
    {
        $this->reproject($shipment);
    }

    private function reproject(PurchaseOrderShipment $shipment): void
    {
        $purchaseOrder = $shipment->purchaseOrder()->first();

        if ($purchaseOrder !== null) {
            $this->projector->sync($purchaseOrder);
        }
    }
}
