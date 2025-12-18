<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Part */
class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $onHand = $this->floatAttribute('aggregate_on_hand') ?? 0.0;
        $minStock = $this->floatAttribute('setting_min_qty');

        return [
            'id' => (string) $this->getKey(),
            'sku' => $this->part_number,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'uom' => $this->uom,
            'default_uom' => $this->uom,
            'on_hand' => $onHand,
            'sites_count' => $this->sitesCount(),
            'status' => $this->active ? 'active' : 'inactive',
            'active' => (bool) $this->active,
            'min_stock' => $minStock,
            'reorder_qty' => $this->floatAttribute('setting_reorder_qty'),
            'lead_time_days' => $this->intAttribute('setting_lead_time_days'),
            'below_min' => $minStock !== null ? $onHand < $minStock : false,
            'default_location_id' => $this->default_location_code,
            'attributes' => $this->attributes ?? null,
            'stock_by_location' => $this->stockByLocation(),
            'attachments' => $this->attachmentPayloads(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachmentPayloads(): array
    {
        if (! $this->relationLoaded('documents')) {
            return [];
        }

        return $this->documents
            ->map(static function ($document): array {
                return [
                    'id' => (int) $document->getKey(),
                    'filename' => $document->filename,
                    'mime' => $document->mime,
                    'size_bytes' => (int) ($document->size_bytes ?? 0),
                    'created_at' => $document->created_at?->toIso8601String(),
                    'download_url' => method_exists($document, 'temporaryDownloadUrl')
                        ? $document->temporaryDownloadUrl()
                        : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockByLocation(): array
    {
        if (! $this->relationLoaded('inventories')) {
            return [];
        }

        return $this->inventories->map(static function ($inventory): array {
            $warehouseName = $inventory->warehouse?->name;
            $locationName = $inventory->bin?->name ?? $warehouseName ?? 'Unassigned';
            $identifier = $inventory->bin_id ?? $inventory->warehouse_id ?? $inventory->id;

            return [
                'id' => (string) $identifier,
                'name' => $locationName,
                'site_name' => $warehouseName,
                'type' => $inventory->bin_id !== null ? 'bin' : 'site',
                'code' => $inventory->bin?->code ?? $inventory->warehouse?->code,
                'on_hand' => (float) $inventory->on_hand,
                'reserved' => (float) $inventory->allocated,
                'available' => (float) max(($inventory->on_hand - $inventory->allocated), 0),
                'supports_negative' => false,
            ];
        })->all();
    }

    private function floatAttribute(string $key): ?float
    {
        $value = $this->getAttribute($key);

        return $value === null ? null : (float) $value;
    }

    private function intAttribute(string $key): ?int
    {
        $value = $this->getAttribute($key);

        return $value === null ? null : (int) $value;
    }

    private function sitesCount(): int
    {
        $aggregate = $this->getAttribute('aggregate_sites_count');

        if ($aggregate !== null) {
            return (int) $aggregate;
        }

        if (! $this->relationLoaded('inventories')) {
            return 0;
        }

        return $this->inventories
            ->pluck('warehouse_id')
            ->filter()
            ->unique()
            ->count();
    }
}
