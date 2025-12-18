<?php

namespace App\Actions\Inventory;

use App\Models\Inventory;
use App\Models\Part;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ShowInventoryItemAction
{
    public function execute(int $companyId, int $itemId): ?Part
    {
        $query = Part::query()
            ->select('parts.*')
            ->where('parts.company_id', $companyId)
            ->where('parts.id', $itemId)
            ->with([
                'inventorySetting',
                'inventories' => function (HasMany $inventoryQuery): void {
                    $inventoryQuery
                        ->with([
                            'warehouse:id,company_id,name,code',
                            'bin:id,warehouse_id,name,code',
                        ])
                        ->orderBy('warehouse_id')
                        ->orderBy('bin_id');
                },
            ]);

        $inventoryTotals = Inventory::query()
            ->select('company_id', 'part_id')
            ->selectRaw('SUM(on_hand) as total_on_hand')
            ->selectRaw('SUM(allocated) as total_allocated')
            ->selectRaw('SUM(on_order) as total_on_order')
            ->selectRaw('COUNT(DISTINCT warehouse_id) as sites_count')
            ->groupBy('company_id', 'part_id');

        $query->leftJoinSub($inventoryTotals, 'inventory_totals', function ($join): void {
            $join->on('inventory_totals.part_id', '=', 'parts.id')
                ->on('inventory_totals.company_id', '=', 'parts.company_id');
        });

        $query->leftJoin('inventory_settings as inv_settings', function ($join): void {
            $join->on('inv_settings.part_id', '=', 'parts.id')
                ->on('inv_settings.company_id', '=', 'parts.company_id');
        });

        $query->addSelect([
            DB::raw('COALESCE(inventory_totals.total_on_hand, 0) as aggregate_on_hand'),
            DB::raw('COALESCE(inventory_totals.total_allocated, 0) as aggregate_allocated'),
            DB::raw('COALESCE(inventory_totals.total_on_order, 0) as aggregate_on_order'),
            DB::raw('COALESCE(inventory_totals.sites_count, 0) as aggregate_sites_count'),
            DB::raw('inv_settings.min_qty as setting_min_qty'),
            DB::raw('inv_settings.reorder_qty as setting_reorder_qty'),
            DB::raw('inv_settings.lead_time_days as setting_lead_time_days'),
        ]);

        return $query->first();
    }
}
