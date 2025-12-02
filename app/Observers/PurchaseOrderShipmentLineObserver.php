<?php

namespace App\Observers;

use App\Models\PurchaseOrderShipmentLine;
use App\Support\Orders\SalesOrderProjector;

class PurchaseOrderShipmentLineObserver
{
    public function __construct(private readonly SalesOrderProjector $projector)
    {
    }

    public function saved(PurchaseOrderShipmentLine $shipmentLine): void
    {
        $this->reproject($shipmentLine);
    }

    public function deleted(PurchaseOrderShipmentLine $shipmentLine): void
    {
        $this->reproject($shipmentLine);
    }

    public function restored(PurchaseOrderShipmentLine $shipmentLine): void
    {
        $this->reproject($shipmentLine);
    }

    public function forceDeleted(PurchaseOrderShipmentLine $shipmentLine): void
    {
        $this->reproject($shipmentLine);
    }

    private function reproject(PurchaseOrderShipmentLine $shipmentLine): void
    {
        $shipment = $shipmentLine->shipment()->first();

        if ($shipment === null) {
            return;
        }

        $purchaseOrder = $shipment->purchaseOrder()->first();

        if ($purchaseOrder !== null) {
            $this->projector->sync($purchaseOrder);
        }
    }
}
