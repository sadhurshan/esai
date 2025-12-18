<?php

namespace App\Actions\Inventory;

use App\Enums\ReorderStatus;
use App\Models\Inventory;
use App\Models\Part;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ListLowStockItemsAction
{
    /**
     * @param  array{
     *     category?: string|null,
     *     site_id?: int|string|null,
     *     location_id?: int|string|null,
     * }  $filters
     */
    public function execute(int $companyId, array $filters, int $perPage, ?string $cursor = null): CursorPaginator
    {
        $query = Part::query()
            ->select('parts.*')
            ->where('parts.company_id', $companyId)
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
                'reorderSuggestions' => function ($suggestions): void {
                    $suggestions
                        ->where('status', ReorderStatus::Open)
                        ->orderBy('horizon_start');
                },
            ]);

        $inventoryTotals = Inventory::query()
            ->select('company_id', 'part_id')
            ->selectRaw('SUM(on_hand) as total_on_hand')
            ->selectRaw('SUM(allocated) as total_allocated')
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
            DB::raw('COALESCE(inventory_totals.sites_count, 0) as aggregate_sites_count'),
            DB::raw('inv_settings.min_qty as setting_min_qty'),
            DB::raw('inv_settings.reorder_qty as setting_reorder_qty'),
            DB::raw('inv_settings.lead_time_days as setting_lead_time_days'),
        ]);

        $query->whereNotNull('inv_settings.min_qty')
            ->whereRaw('COALESCE(inventory_totals.total_on_hand, 0) < inv_settings.min_qty');

        if (! empty($filters['category'])) {
            $query->where('parts.category', 'like', '%' . trim((string) $filters['category']) . '%');
        }

        $siteId = $this->asInt($filters['site_id'] ?? null);
        if ($siteId !== null) {
            $query->whereHas('inventories', function (Builder $inventory) use ($siteId): void {
                $inventory->where('warehouse_id', $siteId);
            });
        }

        $locationId = $this->asInt($filters['location_id'] ?? null);
        if ($locationId !== null) {
            $query->whereHas('inventories', function (Builder $inventory) use ($locationId): void {
                $inventory->where('bin_id', $locationId);
            });
        }

        $query->orderByRaw('COALESCE(inventory_totals.total_on_hand, 0) - inv_settings.min_qty asc')
            ->orderBy('parts.id');

        return $query->cursorPaginate($perPage, ['parts.*'], 'cursor', $cursor);
    }

    private function asInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }
}
