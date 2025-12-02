<?php

namespace App\Observers;

use App\Models\PurchaseOrderLine;
use App\Support\Orders\SalesOrderProjector;

class PurchaseOrderLineObserver
{
    public function __construct(private readonly SalesOrderProjector $projector)
    {
    }

    public function saved(PurchaseOrderLine $line): void
    {
        $this->reproject($line);
    }

    public function deleted(PurchaseOrderLine $line): void
    {
        $this->reproject($line);
    }

    public function restored(PurchaseOrderLine $line): void
    {
        $this->reproject($line);
    }

    public function forceDeleted(PurchaseOrderLine $line): void
    {
        $this->reproject($line);
    }

    private function reproject(PurchaseOrderLine $line): void
    {
        $purchaseOrder = $line->purchaseOrder()->first();

        if ($purchaseOrder !== null) {
            $this->projector->sync($purchaseOrder);
        }
    }
}
