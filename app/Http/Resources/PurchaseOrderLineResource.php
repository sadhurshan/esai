<?php

namespace App\Http\Resources;

use App\Models\Currency;
use App\Models\LineTax;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin \App\Models\PurchaseOrderLine */
class PurchaseOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = strtoupper($this->currency ?? $this->purchaseOrder?->currency ?? 'USD');
        $minorUnit = $this->minorUnitFor($currency);

        $unitPriceMinor = $this->unit_price_minor ?? Money::fromDecimal((float) $this->unit_price, $currency, $minorUnit)->amountMinor();
        $unitMoney = Money::fromMinor($unitPriceMinor, $currency);

        $quantity = (float) $this->quantity;
        $lineSubtotalMoney = $unitMoney->multiply($quantity);

        /** @var Collection<int, LineTax> $taxes */
        $taxes = $this->whenLoaded('taxes', fn () => $this->taxes, collect());

        $taxTotalMinor = $taxes instanceof Collection ? (int) $taxes->sum('amount_minor') : 0;
        $taxMoney = Money::fromMinor($taxTotalMinor, $currency);
        $lineTotalMoney = $lineSubtotalMoney->add($taxMoney);

        $invoicedQuantity = $this->relationLoaded('invoiceLines') ? (int) $this->invoiceLines->sum('quantity') : null;
        $remainingQuantity = $invoicedQuantity !== null ? max((int) $quantity - $invoicedQuantity, 0) : null;

        return [
            'id' => $this->getKey(),
            'line_no' => $this->line_no,
            'description' => $this->description,
            'quantity' => $quantity,
            'uom' => $this->uom,
            'currency' => $currency,
            'unit_price' => (float) $unitMoney->toDecimal($minorUnit),
            'unit_price_minor' => $unitPriceMinor,
            'line_subtotal' => (float) $lineSubtotalMoney->toDecimal($minorUnit),
            'line_subtotal_minor' => $lineSubtotalMoney->amountMinor(),
            'tax_total' => (float) $taxMoney->toDecimal($minorUnit),
            'tax_total_minor' => $taxTotalMinor,
            'line_total' => (float) $lineTotalMoney->toDecimal($minorUnit),
            'line_total_minor' => $lineTotalMoney->amountMinor(),
            'delivery_date' => optional($this->delivery_date)?->toDateString(),
            'invoiced_quantity' => $invoicedQuantity,
            'remaining_quantity' => $remainingQuantity,
            'taxes' => $this->whenLoaded('taxes', fn () => $taxes->map(fn (LineTax $tax): array => [
                'id' => $tax->getKey(),
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
        static $cache = [];

        if (! array_key_exists($currency, $cache)) {
            $record = Currency::query()->where('code', $currency)->first();
            $cache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) $cache[$currency];
    }
}
