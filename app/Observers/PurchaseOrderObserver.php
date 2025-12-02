<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Support\Orders\SalesOrderProjector;

class PurchaseOrderObserver
{
    public function __construct(private readonly SalesOrderProjector $projector)
    {
    }

    public function saved(PurchaseOrder $purchaseOrder): void
    {
        $this->projector->sync($purchaseOrder);
    }

    public function deleted(PurchaseOrder $purchaseOrder): void
    {
        $this->projector->deleteFor($purchaseOrder);
    }

    public function restored(PurchaseOrder $purchaseOrder): void
    {
        $this->projector->sync($purchaseOrder);
    }

    public function forceDeleted(PurchaseOrder $purchaseOrder): void
    {
        $this->projector->deleteFor($purchaseOrder);
    }
}
