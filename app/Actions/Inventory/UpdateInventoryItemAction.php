<?php

namespace App\Actions\Inventory;

use App\Actions\Inventory\Concerns\InteractsWithInventoryItems;
use App\Models\Part;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateInventoryItemAction
{
    use InteractsWithInventoryItems;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Part $part, array $payload): Part
    {
        return DB::transaction(function () use ($part, $payload): Part {
            $updates = [];

            if (array_key_exists('sku', $payload)) {
                $updates['part_number'] = $payload['sku'];
            }

            if (array_key_exists('name', $payload)) {
                $updates['name'] = $payload['name'];
            }

            if (array_key_exists('description', $payload)) {
                $updates['description'] = $payload['description'];
            }

            if (array_key_exists('category', $payload)) {
                $updates['category'] = $payload['category'];
            }

            if (array_key_exists('spec', $payload)) {
                $updates['spec'] = $payload['spec'];
            }

            if (array_key_exists('uom', $payload)) {
                $updates['uom'] = $payload['uom'];
                $updates['base_uom_id'] = $this->resolveUomId($payload['uom']);
            }

            if (array_key_exists('attributes', $payload)) {
                $updates['attributes'] = $this->normaliseAttributes($payload['attributes']);
            }

            if (array_key_exists('default_location_id', $payload)) {
                $updates['default_location_code'] = $payload['default_location_id'];
            }

            if (array_key_exists('active', $payload)) {
                $updates['active'] = (bool) $payload['active'];
            }

            $before = $updates === []
                ? []
                : Arr::only($part->getOriginal(), array_keys($updates));

            $part->fill($updates);

            if ($part->isDirty()) {
                $part->save();

                $this->auditLogger->updated(
                    $part,
                    $before,
                    Arr::only($part->getAttributes(), array_keys($updates))
                );
            }

            $settingSync = $this->syncInventorySettings(
                (int) $part->company_id,
                (int) $part->getKey(),
                $payload
            );

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
