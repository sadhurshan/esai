<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Part */
class LowStockAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $onHand = $this->floatAttribute('aggregate_on_hand') ?? 0.0;
        $minStock = $this->floatAttribute('setting_min_qty') ?? 0.0;
        $primaryLocation = $this->primaryLocation();
        $suggestion = $this->relationLoaded('reorderSuggestions')
            ? $this->reorderSuggestions->first()
            : null;

        return [
            'item_id' => (string) $this->getKey(),
            'sku' => $this->part_number,
            'name' => $this->name,
            'category' => $this->category,
            'on_hand' => $onHand,
            'min_stock' => $minStock,
            'reorder_qty' => $this->floatAttribute('setting_reorder_qty'),
            'lead_time_days' => $this->intAttribute('setting_lead_time_days'),
            'uom' => $this->uom,
            'location_name' => $primaryLocation['location'] ?? null,
            'site_name' => $primaryLocation['site'] ?? null,
            'suggested_reorder_date' => $suggestion?->horizon_start?->toDateString(),
        ];
    }

    /**
     * @return array{location?: string|null, site?: string|null}
     */
    private function primaryLocation(): array
    {
        if (! $this->relationLoaded('inventories') || $this->inventories->isEmpty()) {
            return [];
        }

        $location = $this->inventories
            ->sortBy(fn ($inventory) => (float) $inventory->on_hand)
            ->first();

        if ($location === null) {
            return [];
        }

        return [
            'location' => $location->bin?->name ?? $location->warehouse?->name,
            'site' => $location->warehouse?->name,
        ];
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
}
