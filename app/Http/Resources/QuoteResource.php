<?php

namespace App\Http\Resources;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Quote */
class QuoteResource extends JsonResource
{
    private static array $minorUnitCache = [];

    public function toArray(Request $request): array
    {
        $currency = strtoupper($this->currency ?? 'USD');
        $minorUnit = $this->minorUnitFor($currency);

        $subtotalMinor = $this->subtotal_minor ?? $this->decimalToMinor($this->subtotal, $currency, $minorUnit);
        $taxMinor = $this->tax_amount_minor ?? $this->decimalToMinor($this->tax_amount, $currency, $minorUnit);
        $totalMinor = $this->total_minor ?? $this->decimalToMinor($this->total, $currency, $minorUnit);

        $attachments = $this->relationLoaded('documents') ? $this->documents : null;

        return [
            'id' => (string) $this->getRouteKey(),
            'rfq_id' => $this->rfq_id !== null ? (int) $this->rfq_id : null,
            'supplier_id' => $this->supplier_id !== null ? (int) $this->supplier_id : null,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->getKey(),
                'name' => $this->supplier?->name,
            ]),
            'currency' => $currency,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => $this->formatMinor($subtotalMinor, $currency, $minorUnit),
            'subtotal_minor' => $subtotalMinor,
            'tax_amount' => $this->formatMinor($taxMinor, $currency, $minorUnit),
            'tax_amount_minor' => $taxMinor,
            'total_price' => $this->formatMinor($totalMinor, $currency, $minorUnit),
            'total_price_minor' => $totalMinor,
            'total' => $this->formatMinor($totalMinor, $currency, $minorUnit), // legacy alias
            'total_minor' => $totalMinor,
            'min_order_qty' => $this->min_order_qty,
            'lead_time_days' => $this->lead_time_days,
            'notes' => $this->notes,
            'note' => $this->notes, // legacy alias
            'status' => $this->status,
            'revision_no' => $this->revision_no,
            'submitted_by' => $this->submitted_by,
            'submitted_at' => optional($this->submitted_at ?? $this->created_at)?->toIso8601String(),
            'withdrawn_at' => optional($this->withdrawn_at)?->toIso8601String(),
            'withdraw_reason' => $this->withdraw_reason,
            'attachments_count' => $this->attachments_count ?? ($attachments?->count() ?? 0),
            'items' => $this->whenLoaded('items', fn () => $this->items
                ->map(fn ($item) => (new QuoteItemResource($item))->toArray($request))
                ->values()
                ->all(), []),
            'attachments' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => [
                'id' => (string) $document->getRouteKey(),
                'filename' => $document->filename,
                'path' => $document->path,
                'mime' => $document->mime,
                'size_bytes' => $document->size_bytes,
            ])->all()),
            'revisions' => $this->whenLoaded('revisions', fn () => $this->revisions
                ->map(fn ($revision) => (new QuoteRevisionResource($revision))->toArray($request))
                ->values()
                ->all(), []),
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

    private function formatMinor(int $amountMinor, string $currency, int $minorUnit): string
    {
        return number_format($amountMinor / (10 ** $minorUnit), $minorUnit, '.', '');
    }

    private function decimalToMinor(mixed $value, string $currency, int $minorUnit): int
    {
        if ($value === null) {
            return 0;
        }

        return (int) round(((float) $value) * (10 ** $minorUnit));
    }
}
