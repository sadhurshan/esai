<?php

namespace App\Actions\Inventory;

use App\Models\Part;
use App\Models\PartPreferredSupplier;
use App\Models\Supplier;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SyncPreferredSuppliersAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param list<array{supplier_id:int|null,name:?string,priority:int,notes:?string}> $preferences
     */
    public function execute(Part $part, array $preferences): void
    {
        $normalized = $this->normalizePreferences((int) $part->company_id, $preferences);

        $retained = [];

        foreach ($normalized as $entry) {
            $model = PartPreferredSupplier::query()->firstOrNew([
                'company_id' => $part->company_id,
                'part_id' => $part->getKey(),
                'priority' => $entry['priority'],
            ]);

            $before = $model->exists ? Arr::only($model->getAttributes(), ['supplier_id', 'supplier_name', 'priority', 'notes']) : [];

            $model->fill([
                'supplier_id' => $entry['supplier_id'],
                'supplier_name' => $entry['name'],
                'notes' => $entry['notes'],
            ]);

            if ($model->isDirty() || ! $model->exists) {
                $model->save();

                $after = Arr::only($model->getAttributes(), ['supplier_id', 'supplier_name', 'priority', 'notes']);

                if ($model->wasRecentlyCreated) {
                    $this->auditLogger->created($model, $after);
                } else {
                    $this->auditLogger->updated($model, $before, $after);
                }
            }

            $retained[] = $model->getKey();
        }

        $obsolete = PartPreferredSupplier::query()
            ->forCompany((int) $part->company_id)
            ->where('part_id', $part->getKey())
            ->when($retained !== [], static fn ($query) => $query->whereNotIn('id', $retained))
            ->get();

        foreach ($obsolete as $preference) {
            $before = Arr::only($preference->getAttributes(), ['supplier_id', 'supplier_name', 'priority', 'notes']);
            $preference->delete();
            $this->auditLogger->deleted($preference, $before);
        }
    }

    /**
     * @param list<array{supplier_id:int|string|null,name:?string,priority:int|null,notes:?string}> $preferences
     * @return list<array{supplier_id:?int,name:?string,priority:int,notes:?string}>
     */
    private function normalizePreferences(int $companyId, array $preferences): array
    {
        $entries = [];

        foreach ($preferences as $index => $preference) {
            if (! is_array($preference)) {
                continue;
            }

            $supplierId = $this->coerceId($preference['supplier_id'] ?? null);
            $name = $this->stringValue($preference['name'] ?? null);

            if ($supplierId === null && $name === null) {
                continue;
            }

            if ($supplierId === null && $name !== null) {
                $supplierId = $this->matchSupplierByName($companyId, $name);
            }

            $priority = $this->normalizePriority($preference['priority'] ?? null) ?? ($index + 1);
            $notes = $this->stringValue($preference['notes'] ?? null);

            $entries[] = [
                'supplier_id' => $supplierId,
                'name' => $name,
                'priority' => $priority,
                'notes' => $notes,
                'index' => $index,
            ];
        }

        if ($entries === []) {
            return [];
        }

        $collection = collect($entries)
            ->sortBy([['priority', 'asc'], ['index', 'asc']])
            ->values()
            ->take(5);

        return $collection
            ->map(fn (array $entry, int $offset): array => [
                'supplier_id' => $entry['supplier_id'],
                'name' => $entry['name'],
                'priority' => $offset + 1,
                'notes' => $entry['notes'],
            ])
            ->all();
    }

    private function coerceId(null|int|string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }

    private function matchSupplierByName(int $companyId, string $name): ?int
    {
        $normalized = Str::lower(trim($name));

        if ($normalized === '') {
            return null;
        }

        return Supplier::query()
            ->forCompany($companyId)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->value('id');
    }

    private function normalizePriority(mixed $value): ?int
    {
        if (is_int($value)) {
            return $this->clampPriority($value);
        }

        if (is_string($value) && ctype_digit($value)) {
            return $this->clampPriority((int) $value);
        }

        return null;
    }

    private function clampPriority(int $value): int
    {
        return max(1, min(5, $value));
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
