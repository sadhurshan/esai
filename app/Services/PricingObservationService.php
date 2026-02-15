<?php

namespace App\Services;

use App\Models\PricingObservation;
use App\Models\Quote;
use App\Models\QuoteRevision;
use Illuminate\Support\Str;

class PricingObservationService
{
    public function recordQuoteRevision(Quote $quote, QuoteRevision $revision): void
    {
        $quote->loadMissing(['items.rfqItem', 'rfq', 'supplier']);

        PricingObservation::query()
            ->where('quote_id', $quote->id)
            ->where('revision_no', $revision->revision_no)
            ->delete();

        foreach ($quote->items as $item) {
            if ($item->unit_price_minor === null) {
                continue;
            }

            $rfqItem = $item->rfqItem;
            $rfq = $quote->rfq;

            $process = $this->normalizeProcess($rfqItem?->method ?? $rfq?->method);
            $material = $this->normalizeValue($rfqItem?->material ?? $rfq?->material);
            $finish = $this->normalizeValue($rfqItem?->finish ?? $rfq?->finish);
            $region = $this->normalizeValue($quote->supplier?->country ?? $rfq?->delivery_location);

            PricingObservation::create([
                'company_id' => $quote->company_id,
                'supplier_id' => $quote->supplier_id,
                'rfq_id' => $quote->rfq_id,
                'rfq_item_id' => $item->rfq_item_id,
                'quote_id' => $quote->id,
                'quote_item_id' => $item->id,
                'revision_no' => $revision->revision_no,
                'process' => $process,
                'material' => $material,
                'finish' => $finish,
                'region' => $region,
                'quantity' => $rfqItem?->quantity ?? $rfq?->quantity_total,
                'currency' => $item->currency ?? $quote->currency,
                'unit_price_minor' => $item->unit_price_minor,
                'source_type' => 'quote_item',
                'observed_at' => $revision->created_at ?? now(),
                'meta' => [
                    'rfq_title' => $rfq?->title,
                ],
            ]);
        }
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)->lower()->trim()->value();

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeProcess(?string $value): ?string
    {
        $normalized = $this->normalizeValue($value);

        if ($normalized === null) {
            return null;
        }

        return match ($normalized) {
            'cnc', 'cnc milling', 'cnc turning' => 'cnc',
            'sheet metal', 'sheet_metal' => 'sheet_metal',
            'injection molding', 'injection_molding' => 'injection_molding',
            'additive', '3d printing', '3d_printing' => '3d_printing',
            default => $normalized,
        };
    }
}
