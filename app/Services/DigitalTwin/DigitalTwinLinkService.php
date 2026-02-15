<?php

namespace App\Services\DigitalTwin;

use App\Enums\DigitalTwinAuditEvent as DigitalTwinAuditEventEnum;
use App\Enums\DocumentCategory;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAuditEvent;
use App\Models\Document;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DigitalTwinLinkService
{
    public function linkRfqCreation(RFQ $rfq, User $actor): void
    {
        $twin = $this->resolveLinkedTwin($rfq);

        if (! $twin) {
            return;
        }

        $this->updateLinkedRfqs($twin, $actor, $rfq, 'rfq_create');
    }

    public function linkRfqDocument(RFQ $rfq, Document $document, User $actor, string $source): void
    {
        $twin = $this->resolveLinkedTwin($rfq);

        if (! $twin) {
            return;
        }

        $entry = [
            'document_id' => (int) $document->id,
            'rfq_id' => (int) $rfq->id,
            'kind' => $document->kind,
            'category' => $document->category,
            'source' => $source,
            'linked_at' => now()->toIso8601String(),
        ];

        $this->updateLinkedDocuments($twin, $actor, $entry, ['document_id' => (int) $document->id]);
    }

    public function linkQuoteSubmission(RFQ $rfq, Quote $quote, ?User $actor): void
    {
        $twin = $this->resolveLinkedTwin($rfq);

        if (! $twin) {
            return;
        }

        $entry = [
            'quote_id' => (int) $quote->id,
            'supplier_id' => (int) $quote->supplier_id,
            'submitted_at' => optional($quote->submitted_at ?? $quote->created_at)->toIso8601String(),
            'lead_time_days' => (int) ($quote->lead_time_days ?? 0),
            'total_price_minor' => (int) ($quote->total_price_minor ?? 0),
            'currency' => $quote->currency,
        ];

        $this->updateLinkedQuotes($twin, $actor, $entry, ['quote_id' => (int) $quote->id]);
        $this->recordQuoteProcessNotes($twin, $actor, $quote);
        $this->updateWarrantySummary($twin, $actor, $quote->notes, $quote->payment_terms);
        // TODO: clarify whether PO payment terms should override warranty summaries.
    }

    public function recordClarification(RFQ $rfq, RfqClarification $clarification, User $actor): void
    {
        $twin = $this->resolveLinkedTwin($rfq);

        if (! $twin) {
            return;
        }

        $note = [
            'clarification_id' => (int) $clarification->id,
            'rfq_id' => (int) $rfq->id,
            'author_id' => (int) $actor->id,
            'type' => $clarification->type->value,
            'message' => $clarification->message,
            'created_at' => optional($clarification->created_at)->toIso8601String(),
            'source' => 'rfq_clarification',
        ];

        $this->appendProcessNote($twin, $actor, $note, ['clarification_id' => (int) $clarification->id]);
    }

    /**
     * @param  array<int, int>  $supplierIds
     */
    public function linkSupplierCertificates(RFQ $rfq, array $supplierIds, ?User $actor): void
    {
        $twin = $this->resolveLinkedTwin($rfq);

        if (! $twin) {
            return;
        }

        $supplierIds = array_values(array_filter(array_map('intval', $supplierIds)));

        if ($supplierIds === []) {
            return;
        }

        $documents = SupplierDocument::query()
            ->whereIn('supplier_id', $supplierIds)
            ->whereIn('status', ['valid', 'expiring'])
            ->get();

        foreach ($documents as $document) {
            $entry = [
                'document_id' => (int) $document->document_id,
                'supplier_document_id' => (int) $document->id,
                'supplier_id' => (int) $document->supplier_id,
                'kind' => 'certificate',
                'category' => DocumentCategory::Qa->value,
                'source' => 'supplier_certificate',
                'expires_at' => optional($document->expires_at)->toDateString(),
                'linked_at' => now()->toIso8601String(),
            ];

            $this->updateLinkedDocuments($twin, $actor, $entry, [
                'document_id' => (int) $document->document_id,
                'source' => 'supplier_certificate',
            ]);
        }
    }

    public function updateWarrantySummary(DigitalTwin $twin, ?User $actor, ?string $notes, ?string $paymentTerms): void
    {
        $summary = $this->extractWarrantySummary($notes, $paymentTerms);

        if ($summary === null) {
            return;
        }

        $extra = $this->normalizeExtra($twin);
        $existing = Arr::get($extra, 'warranty_summary');

        if ($existing === $summary) {
            return;
        }

        $extra['warranty_summary'] = $summary;
        $extra['warranty_updated_at'] = now()->toIso8601String();

        $this->saveExtra($twin, $extra, $actor, 'warranty_summary_updated');
    }

    private function resolveLinkedTwin(RFQ $rfq): ?DigitalTwin
    {
        $meta = $this->normalizeMeta($rfq->meta ?? null);
        $digitalTwinId = Arr::get($meta, 'digital_twin_id');

        if (! $digitalTwinId) {
            return null;
        }

        return CompanyContext::bypass(function () use ($digitalTwinId, $rfq): ?DigitalTwin {
            $twin = DigitalTwin::query()->whereKey((int) $digitalTwinId)->first();

            if (! $twin) {
                return null;
            }

            if ($twin->company_id !== null && (int) $twin->company_id !== (int) $rfq->company_id) {
                return null;
            }

            // TODO: clarify if library twins (company_id null) should be linkable to tenant RFQs.

            return $twin;
        });
    }

    private function updateLinkedRfqs(DigitalTwin $twin, User $actor, RFQ $rfq, string $source): void
    {
        $extra = $this->normalizeExtra($twin);
        $linked = $this->normalizeList(Arr::get($extra, 'linked_rfqs'));

        $entry = [
            'rfq_id' => (int) $rfq->id,
            'linked_at' => now()->toIso8601String(),
            'source' => $source,
        ];

        $linked = $this->upsertByKey($linked, 'rfq_id', (int) $rfq->id, $entry);
        $extra['linked_rfqs'] = $linked;

        $this->saveExtra($twin, $extra, $actor, 'rfq_linked', ['rfq_id' => (int) $rfq->id]);
    }

    private function updateLinkedDocuments(DigitalTwin $twin, ?User $actor, array $entry, array $match): void
    {
        $extra = $this->normalizeExtra($twin);
        $linked = $this->normalizeList(Arr::get($extra, 'linked_documents'));

        $linked = $this->upsertByComposite($linked, $match, $entry);
        $extra['linked_documents'] = $linked;

        $this->saveExtra($twin, $extra, $actor, 'document_linked', $match);
    }

    private function updateLinkedQuotes(DigitalTwin $twin, ?User $actor, array $entry, array $match): void
    {
        $extra = $this->normalizeExtra($twin);
        $linked = $this->normalizeList(Arr::get($extra, 'linked_quotes'));

        $linked = $this->upsertByComposite($linked, $match, $entry);
        $extra['linked_quotes'] = $linked;

        $this->saveExtra($twin, $extra, $actor, 'quote_linked', $match);
    }

    private function appendProcessNote(DigitalTwin $twin, ?User $actor, array $note, array $meta): void
    {
        $extra = $this->normalizeExtra($twin);
        $notes = $this->normalizeList(Arr::get($extra, 'process_notes'));
        $notes[] = $note;
        $extra['process_notes'] = $notes;

        $this->saveExtra($twin, $extra, $actor, 'process_note_added', $meta);
    }

    private function recordQuoteProcessNotes(DigitalTwin $twin, ?User $actor, Quote $quote): void
    {
        if (! $quote->notes) {
            return;
        }

        $note = [
            'quote_id' => (int) $quote->id,
            'author_id' => $actor?->id,
            'type' => 'quote_note',
            'message' => $quote->notes,
            'created_at' => optional($quote->submitted_at ?? $quote->updated_at)->toIso8601String(),
            'source' => 'quote',
        ];

        $this->appendProcessNote($twin, $actor, $note, ['quote_id' => (int) $quote->id]);
    }

    private function saveExtra(DigitalTwin $twin, array $extra, ?User $actor, string $action, array $meta = []): void
    {
        DB::transaction(function () use ($twin, $extra, $actor, $action, $meta): void {
            $before = $this->normalizeExtra($twin);

            if ($before === $extra) {
                return;
            }

            $twin->extra = $extra;
            $twin->save();

            $this->recordAudit($twin, $actor, $action, $meta);
        });
    }

    private function recordAudit(DigitalTwin $twin, ?User $actor, string $action, array $meta = []): void
    {
        $payload = array_merge(['action' => $action], $meta);

        DigitalTwinAuditEvent::create([
            'digital_twin_id' => $twin->id,
            'actor_id' => $actor?->id,
            'event' => DigitalTwinAuditEventEnum::Updated,
            'meta' => $payload === [] ? null : $payload,
        ]);
    }

    private function normalizeExtra(DigitalTwin $twin): array
    {
        if (is_array($twin->extra)) {
            return $twin->extra;
        }

        if ($twin->extra === null) {
            return [];
        }

        return (array) $twin->extra;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn ($item) => is_array($item)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function upsertByKey(array $items, string $key, int $value, array $entry): array
    {
        $updated = false;
        $next = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((int) ($item[$key] ?? 0) === $value) {
                $next[] = array_merge($item, $entry);
                $updated = true;
                continue;
            }

            $next[] = $item;
        }

        if (! $updated) {
            $next[] = $entry;
        }

        return array_values($next);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $match
     * @return array<int, array<string, mixed>>
     */
    private function upsertByComposite(array $items, array $match, array $entry): array
    {
        $updated = false;
        $next = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $isMatch = true;
            foreach ($match as $key => $value) {
                if (($item[$key] ?? null) !== $value) {
                    $isMatch = false;
                    break;
                }
            }

            if ($isMatch) {
                $next[] = array_merge($item, $entry);
                $updated = true;
                continue;
            }

            $next[] = $item;
        }

        if (! $updated) {
            $next[] = $entry;
        }

        return array_values($next);
    }

    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if ($meta === null) {
            return [];
        }

        return (array) $meta;
    }

    private function extractWarrantySummary(?string $notes, ?string $paymentTerms): ?string
    {
        $candidates = array_filter([
            $paymentTerms,
            $notes,
        ], static fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($candidates as $text) {
            if (! $this->mentionsWarranty($text)) {
                continue;
            }

            $trimmed = trim($text);

            if (strlen($trimmed) > 240) {
                return substr($trimmed, 0, 237).'...';
            }

            return $trimmed;
        }

        return null;
    }

    private function mentionsWarranty(string $text): bool
    {
        return (bool) preg_match('/warranty|guarantee/i', $text);
    }
}
