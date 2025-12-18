<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

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
        $supplierPayload = $this->formatSupplierPayload();

        return [
            'id' => (string) $this->getRouteKey(),
            'rfq_id' => $this->rfq_id !== null ? (int) $this->rfq_id : null,
            'supplier_id' => $this->supplier_id !== null ? (int) $this->supplier_id : null,
            'supplier' => $supplierPayload,
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
            'incoterm' => $this->incoterm,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'note' => $this->notes, // legacy alias
            'status' => $this->status,
            'revision_no' => $this->revision_no,
            'submitted_by' => $this->submitted_by,
            'submitted_at' => optional($this->submitted_at ?? $this->created_at)?->toIso8601String(),
            'withdrawn_at' => optional($this->withdrawn_at)?->toIso8601String(),
            'withdraw_reason' => $this->withdraw_reason,
            'attachments_count' => $this->attachments_count ?? ($attachments?->count() ?? 0),
            'is_shortlisted' => $this->shortlisted_at !== null,
            'shortlisted_at' => optional($this->shortlisted_at)?->toIso8601String(),
            'shortlisted_by' => $this->shortlisted_by !== null ? (int) $this->shortlisted_by : null,
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

    private function formatSupplierPayload(): ?array
    {
        if (! $this->relationLoaded('supplier') || $this->supplier === null) {
            return null;
        }

        $supplier = $this->supplier;
        $companyPayload = null;

        if ($supplier->relationLoaded('company') && $supplier->company instanceof Company) {
            $companyPayload = $this->formatSupplierCompany($supplier->company);
        }

        return [
            'id' => $supplier->getKey(),
            'name' => $supplier->name,
            'status' => $supplier->status,
            'verified_at' => optional($supplier->verified_at)?->toIso8601String(),
            'rating_avg' => $supplier->rating_avg !== null ? (float) $supplier->rating_avg : null,
            'risk_grade' => $supplier->risk_grade?->value,
            'company' => $companyPayload,
            'compliance' => $this->formatSupplierCompliance($supplier),
        ];
    }

    private function formatSupplierCompany(?Company $company): ?array
    {
        if (! $company instanceof Company) {
            return null;
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
            'supplier_status' => $company->supplier_status?->value,
            'is_verified' => $company->is_verified,
            'verified_at' => optional($company->verified_at)?->toIso8601String(),
        ];
    }

    private function formatSupplierCompliance(Supplier $supplier): array
    {
        /** @var Collection<int, mixed>|null $documents */
        $documents = $supplier->relationLoaded('documents') ? $supplier->documents : null;

        $validCount = $this->resolveDocumentCount($supplier, 'valid', $documents);
        $expiringCount = $this->resolveDocumentCount($supplier, 'expiring', $documents);
        $expiredCount = $this->resolveDocumentCount($supplier, 'expired', $documents);

        $nextExpiring = $documents instanceof Collection
            ? $documents
                ->filter(static fn ($doc) => $doc->status !== 'expired')
                ->sortBy(static fn ($doc) => $doc->expires_at ?? now()->addYears(5))
                ->first()
            : null;

        $documentSummaries = $documents instanceof Collection
            ? $documents
                ->sortBy(static fn ($doc) => $doc->expires_at ?? now()->addYears(5))
                ->take(3)
                ->map(static fn ($doc): array => [
                    'id' => (int) $doc->getKey(),
                    'type' => $doc->type,
                    'status' => $doc->status,
                    'expires_at' => optional($doc->expires_at)?->toIso8601String(),
                ])
                ->values()
                ->all()
            : [];

        return [
            'certificates' => [
                'valid' => $validCount,
                'expiring' => $expiringCount,
                'expired' => $expiredCount,
                'next_expiring_at' => optional($nextExpiring?->expires_at)?->toIso8601String(),
            ],
            'documents' => $documentSummaries,
        ];
    }

    private function resolveDocumentCount(Supplier $supplier, string $status, ?Collection $documents): int
    {
        if ($documents instanceof Collection) {
            return $documents->where('status', $status)->count();
        }

        $attribute = match ($status) {
            'valid' => 'valid_documents_count',
            'expiring' => 'expiring_documents_count',
            'expired' => 'expired_documents_count',
            default => null,
        };

        if ($attribute === null) {
            return 0;
        }

        $value = $supplier->getAttribute($attribute);

        return $value !== null ? (int) $value : 0;
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
