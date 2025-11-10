<?php

namespace App\Http\Resources;

use App\Models\Currency;
use App\Models\LineTax;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin \App\Models\InvoiceLine */
class InvoiceLineResource extends JsonResource
{
    private static array $minorUnitCache = [];

    public function toArray(Request $request): array
    {
        $currency = strtoupper($this->currency ?? $this->invoice?->currency ?? 'USD');
        $minorUnit = $this->minorUnitFor($currency);

        $quantity = (float) $this->quantity;
        $unitPriceDecimal = $this->unit_price_minor !== null
            ? (float) Money::fromMinor((int) $this->unit_price_minor, $currency)->toDecimal($minorUnit)
            : (float) $this->unit_price;

        $netLineTotal = round($quantity * $unitPriceDecimal, $minorUnit);

        /** @var Collection<int, LineTax> $taxes */
        $taxes = $this->whenLoaded('taxes', fn () => $this->taxes, collect());

        $taxTotalMinor = $taxes instanceof Collection ? (int) $taxes->sum('amount_minor') : 0;
        $taxTotal = $taxTotalMinor > 0
            ? (float) Money::fromMinor($taxTotalMinor, $currency)->toDecimal($minorUnit)
            : 0.0;

        $grossLineTotal = round($netLineTotal + $taxTotal, $minorUnit);

        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'po_line_id' => $this->po_line_id,
            'description' => $this->description,
            'quantity' => $quantity,
            'uom' => $this->uom,
            'currency' => $currency,
            'unit_price' => (float) number_format($unitPriceDecimal, $minorUnit, '.', ''),
            'unit_price_minor' => $this->unit_price_minor,
            'line_subtotal' => $netLineTotal,
            'tax_total' => $taxTotal,
            'line_total' => $grossLineTotal,
            'taxes' => $this->whenLoaded('taxes', fn () => $taxes->map(fn (LineTax $tax): array => [
                'id' => $tax->id,
                'tax_code_id' => $tax->tax_code_id,
                'rate_percent' => (float) $tax->rate_percent,
                'amount_minor' => (int) $tax->amount_minor,
                'amount' => (float) Money::fromMinor((int) $tax->amount_minor, $currency)->toDecimal($minorUnit),
                'sequence' => $tax->sequence,
            ])->values()->all()),
        ];
    }

    private function minorUnitFor(string $currency): int
    {
        if (! array_key_exists($currency, self::$minorUnitCache)) {
            $minor = Currency::query()->where('code', $currency)->value('minor_unit');
            self::$minorUnitCache[$currency] = $minor !== null ? (int) $minor : 2;
        }

        return self::$minorUnitCache[$currency];
    }
}
