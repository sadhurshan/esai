<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CompanyMoneySetting */
class MoneySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'base_currency' => $this->formatCurrency('base'),
            'pricing_currency' => $this->formatCurrency('pricing'),
            'fx_source' => $this->fx_source,
            'price_round_rule' => $this->price_round_rule,
            'tax_regime' => $this->tax_regime,
            'defaults' => $this->defaults_meta ?? [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    private function formatCurrency(string $type): ?array
    {
        $code = $type === 'base' ? $this->base_currency : $this->pricing_currency;

        if ($code === null) {
            return null;
        }

        $relation = null;

        if ($type === 'base' && $this->resource->relationLoaded('baseCurrency')) {
            $relation = $this->baseCurrency;
        }

        if ($type === 'pricing' && $this->resource->relationLoaded('pricingCurrency')) {
            $relation = $this->pricingCurrency;
        }

        return [
            'code' => $code,
            'name' => $relation?->name,
            'minor_unit' => $relation?->minor_unit,
            'symbol' => $relation?->symbol,
        ];
    }
}
