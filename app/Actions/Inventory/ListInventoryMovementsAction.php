<?php

namespace App\Actions\Inventory;

use App\Models\InventoryMovement;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class ListInventoryMovementsAction
{
    /**
     * @param  array{
     *     type?: string|array<int, string>|null,
     *     item_id?: int|string|null,
     *     location_id?: int|string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     * }  $filters
     */
    public function execute(int $companyId, array $filters, int $perPage, ?string $cursor = null): CursorPaginator
    {
        $query = InventoryMovement::query()
            ->where('company_id', $companyId)
            ->withCount('lines')
            ->with([
                'lines' => function (Builder $builder): void {
                    $builder
                        ->with([
                            'fromWarehouse:id,company_id,name,code',
                            'toWarehouse:id,company_id,name,code',
                            'fromBin:id,warehouse_id,name,code',
                            'toBin:id,warehouse_id,name,code',
                        ])
                        ->orderBy('line_number')
                        ->limit(1);
                },
                'creator:id,name',
            ]);

        $types = $this->normalizeTypes($filters['type'] ?? null);
        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        $itemId = $this->asInt($filters['item_id'] ?? null);
        if ($itemId !== null) {
            $query->whereHas('lines', function (Builder $line) use ($itemId): void {
                $line->where('part_id', $itemId);
            });
        }

        $locationId = $this->asInt($filters['location_id'] ?? null);
        if ($locationId !== null) {
            $query->whereHas('lines', function (Builder $line) use ($locationId): void {
                $line->where('from_bin_id', $locationId)
                    ->orWhere('to_bin_id', $locationId)
                    ->orWhere('from_warehouse_id', $locationId)
                    ->orWhere('to_warehouse_id', $locationId);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('moved_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('moved_at', '<=', $filters['date_to']);
        }

        $query->orderByDesc('moved_at')->orderByDesc('id');

        return $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    /**
     * @return list<string>
     */
    private function normalizeTypes(mixed $value): array
    {
        $allowed = ['receipt', 'issue', 'transfer', 'adjust'];
        $candidates = [];

        if (is_string($value) && $value !== '') {
            $candidates[] = strtolower($value);
        } elseif (is_array($value)) {
            foreach ($value as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $candidates[] = strtolower($entry);
                }
            }
        }

        $candidates = array_unique($candidates);

        return array_values(array_filter($candidates, static fn (string $candidate): bool => in_array($candidate, $allowed, true)));
    }

    private function asInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    }
}
