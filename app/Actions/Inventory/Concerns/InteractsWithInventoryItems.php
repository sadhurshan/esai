<?php

namespace App\Actions\Inventory\Concerns;

use App\Models\InventorySetting;
use App\Models\Uom;
use Illuminate\Support\Arr;

trait InteractsWithInventoryItems
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     model: InventorySetting,
     *     action: 'created'|'updated',
     *     before: array<string, mixed>,
     *     after: array<string, mixed>,
     * }|null
     */
    protected function syncInventorySettings(int $companyId, int $partId, array $payload): ?array
    {
        $mapping = [
            'min_stock' => 'min_qty',
            'reorder_qty' => 'reorder_qty',
            'lead_time_days' => 'lead_time_days',
        ];

        $updates = [];

        foreach ($mapping as $inputKey => $column) {
            if (array_key_exists($inputKey, $payload)) {
                $updates[$column] = $payload[$inputKey];
            }
        }

        if ($updates === []) {
            return null;
        }

        $setting = InventorySetting::query()->firstOrNew([
            'company_id' => $companyId,
            'part_id' => $partId,
        ]);

        $before = $setting->exists ? Arr::only($setting->getAttributes(), array_keys($updates)) : [];

        $setting->fill($updates);

        if (! $setting->isDirty()) {
            return null;
        }

        $setting->save();

        return [
            'model' => $setting,
            'action' => $setting->wasRecentlyCreated ? 'created' : 'updated',
            'before' => $before,
            'after' => Arr::only($setting->getAttributes(), array_keys($updates)),
        ];
    }

    /**
     * @param  array<string, string|int|float|null>|null  $attributes
     */
    protected function normaliseAttributes(?array $attributes): ?array
    {
        if ($attributes === null) {
            return null;
        }

        $clean = [];

        foreach ($attributes as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $trimmedKey = trim($key);

            if ($trimmedKey === '') {
                continue;
            }

            if ($value === null) {
                $clean[$trimmedKey] = null;
                continue;
            }

            if (is_scalar($value)) {
                $clean[$trimmedKey] = is_string($value) ? trim($value) : $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    protected function resolveUomId(?string $uom): ?int
    {
        if ($uom === null || trim($uom) === '') {
            return null;
        }

        $normalised = strtolower(trim($uom));

        return Uom::query()
            ->whereRaw('LOWER(code) = ?', [$normalised])
            ->value('id');
    }
}
