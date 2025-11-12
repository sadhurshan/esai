<?php

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    private static array $minorUnitCache = [];

    public function toArray(Request $request): array
    {
        $currency = strtoupper($this->currency ?? 'USD');
        $minorUnit = $this->minorUnitFor($currency);

        $subtotalMinor = $this->subtotal_minor ?? $this->decimalToMinor($this->subtotal, $minorUnit);
        $taxMinor = $this->tax_amount_minor ?? $this->decimalToMinor($this->tax_amount, $minorUnit);
        $totalMinor = $this->total_minor ?? $this->decimalToMinor($this->total, $minorUnit);

        return [
            'id' => $this->getKey(),
            'company_id' => $this->company_id,
            'po_number' => $this->po_number,
            'status' => $this->status,
            'currency' => $currency,
            'incoterm' => $this->incoterm,
            'tax_percent' => $this->tax_percent,
            'subtotal' => $this->formatMinor($subtotalMinor, $minorUnit),
            'subtotal_minor' => $subtotalMinor,
            'tax_amount' => $this->formatMinor($taxMinor, $minorUnit),
            'tax_amount_minor' => $taxMinor,
            'total' => $this->formatMinor($totalMinor, $minorUnit),
            'total_minor' => $totalMinor,
            'revision_no' => $this->revision_no,
            'rfq_id' => $this->rfq_id,
            'quote_id' => $this->quote_id,
            'supplier' => $this->when(
                $this->relationLoaded('supplier') || $this->relationLoaded('quote'),
                function () {
                    if ($this->supplier) {
                        return [
                            'id' => $this->supplier->getKey(),
                            'name' => $this->supplier->name,
                        ];
                    }

                    if ($this->quote?->supplier) {
                        return [
                            'id' => $this->quote->supplier->getKey(),
                            'name' => $this->quote->supplier->name,
                        ];
                    }

                    if ($this->quote?->supplier_id) {
                        return [
                            'id' => $this->quote->supplier_id,
                            'name' => null,
                        ];
                    }

                    return null;
                }
            ),
            'rfq' => $this->whenLoaded('rfq', fn () => [
                'id' => $this->rfq?->getKey(),
                'number' => $this->rfq?->number,
                'title' => $this->rfq?->title,
            ]),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines
                ->map(fn ($line) => (new PurchaseOrderLineResource($line))->toArray($request))
                ->values()
                ->all(), []),
            'change_orders' => $this->whenLoaded('changeOrders', fn () => $this->changeOrders
                ->map(fn ($changeOrder) => (new PoChangeOrderResource($changeOrder))->toArray($request))
                ->values()
                ->all(), []),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    private function minorUnitFor(string $currency): int
    {
        if (! array_key_exists($currency, self::$minorUnitCache)) {
            $record = Currency::query()->where('code', $currency)->first();
            self::$minorUnitCache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) self::$minorUnitCache[$currency];
    }

    private function formatMinor(int $amountMinor, int $minorUnit): string
    {
        return number_format($amountMinor / (10 ** $minorUnit), $minorUnit, '.', '');
    }

    private function decimalToMinor(mixed $value, int $minorUnit): int
    {
        if ($value === null) {
            return 0;
        }

        return (int) round(((float) $value) * (10 ** $minorUnit));
    }
}
