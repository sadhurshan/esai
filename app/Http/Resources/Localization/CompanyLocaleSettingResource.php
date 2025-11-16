<?php

namespace App\Http\Resources\Localization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

class CompanyLocaleSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'number_format' => $this->number_format,
            'date_format' => $this->date_format,
            'first_day_of_week' => $this->first_day_of_week,
            'weekend_days' => $this->weekend_days,
            'currency' => [
                'primary' => $this->currency_primary,
                'display_fx' => (bool) $this->currency_display_fx,
            ],
            'uom' => [
                'base_uom' => $this->uom_base,
                'maps' => $this->resolveUomMaps(),
            ],
        ];
    }

    private function resolveUomMaps(): array|stdClass
    {
        if (! is_array($this->uom_maps) || $this->uom_maps === []) {
            return new stdClass();
        }

        return $this->uom_maps;
    }
}
