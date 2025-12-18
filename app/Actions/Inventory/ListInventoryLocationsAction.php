<?php

namespace App\Actions\Inventory;

use App\Models\Bin;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListInventoryLocationsAction
{
    /**
     * @param  array{
     *     type?: string|null,
     *     site_id?: int|string|null,
     *     search?: string|null,
     * }  $filters
     */
    public function execute(int $companyId, array $filters, int $perPage, ?string $cursor = null): CursorPaginator
    {
        $type = $this->normalizeType($filters['type'] ?? null);

        return $type === 'site'
            ? $this->sites($companyId, $filters, $perPage, $cursor)
            : $this->bins($companyId, $filters, $perPage, $cursor);
    }

    /**
     * @param  array{search?: string|null}  $filters
     */
    private function sites(int $companyId, array $filters, int $perPage, ?string $cursor): CursorPaginator
    {
        $query = Warehouse::query()
            ->select('warehouses.*')
            ->where('warehouses.company_id', $companyId);

        $inventoryTotals = Inventory::query()
            ->select('company_id')
            ->selectRaw('warehouse_id as location_id')
            ->selectRaw('SUM(on_hand) as total_on_hand')
            ->selectRaw('SUM(allocated) as total_allocated')
            ->groupBy('company_id', 'warehouse_id');

        $query->leftJoinSub($inventoryTotals, 'inventory_totals', function ($join): void {
            $join->on('inventory_totals.location_id', '=', 'warehouses.id')
                ->on('inventory_totals.company_id', '=', 'warehouses.company_id');
        });

        $query->addSelect([
            DB::raw('COALESCE(inventory_totals.total_on_hand, 0) as aggregate_on_hand'),
            DB::raw('COALESCE(inventory_totals.total_allocated, 0) as aggregate_allocated'),
            DB::raw('\'site\' as location_type'),
        ]);

        if (! empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('warehouses.name', 'like', $term)
                    ->orWhere('warehouses.code', 'like', $term);
            });
        }

        $query->orderBy('warehouses.name');

        return $query->cursorPaginate($perPage, ['warehouses.*'], 'cursor', $cursor);
    }

    /**
     * @param  array{site_id?: int|string|null, search?: string|null}  $filters
     */
    private function bins(int $companyId, array $filters, int $perPage, ?string $cursor): CursorPaginator
    {
        $query = Bin::query()
            ->select('bins.*')
            ->where('bins.company_id', $companyId)
            ->with(['warehouse:id,company_id,name,code']);

        $inventoryTotals = Inventory::query()
            ->select('company_id')
            ->selectRaw('bin_id as location_id')
            ->selectRaw('SUM(on_hand) as total_on_hand')
            ->selectRaw('SUM(allocated) as total_allocated')
            ->groupBy('company_id', 'bin_id');

        $query->leftJoinSub($inventoryTotals, 'inventory_totals', function ($join): void {
            $join->on('inventory_totals.location_id', '=', 'bins.id')
                ->on('inventory_totals.company_id', '=', 'bins.company_id');
        });

        $query->addSelect([
            DB::raw('COALESCE(inventory_totals.total_on_hand, 0) as aggregate_on_hand'),
            DB::raw('COALESCE(inventory_totals.total_allocated, 0) as aggregate_allocated'),
            DB::raw('\'bin\' as location_type'),
        ]);

        $siteId = $this->asInt($filters['site_id'] ?? null);
        if ($siteId !== null) {
            $query->where('bins.warehouse_id', $siteId);
        }

        if (! empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('bins.name', 'like', $term)
                    ->orWhere('bins.code', 'like', $term);
            });
        }

        $query->orderBy('bins.warehouse_id')->orderBy('bins.name');

        return $query->cursorPaginate($perPage, ['bins.*'], 'cursor', $cursor);
    }

    private function normalizeType(mixed $value): string
    {
        $type = is_string($value) ? strtolower($value) : '';

        return in_array($type, ['site', 'bin', 'zone'], true) ? ($type === 'zone' ? 'bin' : $type) : 'bin';
    }

    private function asInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }
}
