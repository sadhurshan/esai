<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\InteractsWithInventoryItems;
use App\Models\Part;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreInventoryItemAction
{
    use InteractsWithInventoryItems;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{
     *     sku: string,
     *     name: string,
     *     uom: string,
     *     category?: string|null,
    *     description?: string|null,
    *     spec?: string|null,
     *     attributes?: array<string, string|int|float|null>|null,
     *     default_location_id?: string|null,
     *     min_stock?: string|float|int|null,
     *     reorder_qty?: string|float|int|null,
     *     lead_time_days?: int|null,
     *     active?: bool|null,
     * }  $payload
     */
    public function execute(int $companyId, array $payload): Part
    {
        return DB::transaction(function () use ($companyId, $payload): Part {
            $attributes = array_key_exists('attributes', $payload)
                ? $this->normaliseAttributes($payload['attributes'])
                : null;

            $part = Part::query()->create([
                'company_id' => $companyId,
                'part_number' => $payload['sku'],
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'] ?? null,
                'uom' => $payload['uom'],
                'base_uom_id' => $this->resolveUomId($payload['uom']),
                'spec' => $payload['spec'] ?? null,
                'attributes' => $attributes,
                'default_location_code' => $payload['default_location_id'] ?? null,
                'active' => array_key_exists('active', $payload) ? (bool) $payload['active'] : true,
            ]);

            $this->auditLogger->created($part, Arr::only($part->getAttributes(), [
                'company_id',
                'part_number',
                'name',
                'description',
                'category',
                'uom',
                'spec',
                'default_location_code',
                'active',
            ]));

            $settingSync = $this->syncInventorySettings($companyId, (int) $part->getKey(), $payload);

            if ($settingSync !== null) {
                $setting = $settingSync['model'];

                if ($settingSync['action'] === 'created') {
                    $this->auditLogger->created($setting, $settingSync['after']);
                } else {
                    $this->auditLogger->updated($setting, $settingSync['before'], $settingSync['after']);
                }
            }

            return $part;
        });
    }
}
