<?php

namespace App\Actions\Inventory;

use App\Models\InventoryMovement;

class ShowInventoryMovementAction
{
    public function execute(int $companyId, int $movementId): ?InventoryMovement
    {
        return InventoryMovement::query()
            ->where('company_id', $companyId)
            ->whereKey($movementId)
            ->with([
                'lines' => function ($query): void {
                    $query
                        ->orderBy('line_number')
                        ->with([
                            'part:id,company_id,part_number,name,uom',
                            'fromWarehouse:id,company_id,name,code',
                            'toWarehouse:id,company_id,name,code',
                            'fromBin:id,company_id,name,code,warehouse_id',
                            'fromBin.warehouse:id,company_id,name,code',
                            'toBin:id,company_id,name,code,warehouse_id',
                            'toBin.warehouse:id,company_id,name,code',
                        ]);
                },
                'creator:id,name',
            ])
            ->withCount('lines')
            ->first();
    }
}
