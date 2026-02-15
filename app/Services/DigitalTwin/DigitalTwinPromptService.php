<?php

namespace App\Services\DigitalTwin;

use App\Enums\RfqItemAwardStatus;
use App\Models\DigitalTwin;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DigitalTwinPromptService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildForRfq(RFQ $rfq): array
    {
        $twin = $this->resolveLinkedTwin($rfq);

        if (! $twin) {
            return [];
        }

        $extra = $this->normalizeExtra($twin);
        $prompts = [];

        $warrantySummary = Arr::get($extra, 'warranty_summary');
        if ($rfq->status === RFQ::STATUS_AWARDED && is_string($warrantySummary) && trim($warrantySummary) !== '') {
            $prompts[] = [
                'type' => 'warranty_reminder',
                'title' => 'Warranty terms recorded',
                'description' => $warrantySummary,
                'cta' => 'Confirm warranty follow-ups',
            ];
        }

        $linkedRfqIds = collect(Arr::get($extra, 'linked_rfqs', []))
            ->filter(static fn ($row) => is_array($row) && isset($row['rfq_id']))
            ->map(static fn ($row) => (int) $row['rfq_id'])
            ->filter()
            ->values()
            ->all();

        if ($linkedRfqIds !== []) {
            $prompts = array_merge($prompts, $this->buildAwardPrompts($linkedRfqIds));
        }

        $expiringCertificates = $this->findExpiringCertificates($extra);
        if ($expiringCertificates !== []) {
            $prompts[] = [
                'type' => 'qa_followup',
                'title' => 'QA certificates expiring soon',
                'description' => sprintf('%d supplier certificate(s) expire within 30 days. Request updated documents.', count($expiringCertificates)),
                'cta' => 'Request certificate updates',
            ];
        }

        return $prompts;
    }

    /**
     * @param  array<int, int>  $linkedRfqIds
     * @return array<int, array<string, mixed>>
     */
    private function buildAwardPrompts(array $linkedRfqIds): array
    {
        return CompanyContext::bypass(function () use ($linkedRfqIds): array {
            $prompts = [];

            $partAwards = RfqItemAward::query()
                ->select('rfq_items.part_number', DB::raw('COUNT(*) as awards_count'))
                ->join('rfq_items', 'rfq_items.id', '=', 'rfq_item_awards.rfq_item_id')
                ->whereIn('rfq_item_awards.rfq_id', $linkedRfqIds)
                ->where('rfq_item_awards.status', RfqItemAwardStatus::Awarded)
                ->groupBy('rfq_items.part_number')
                ->having('awards_count', '>=', 2)
                ->orderByDesc('awards_count')
                ->limit(3)
                ->get();

            if ($partAwards->isNotEmpty()) {
                $topPart = $partAwards->first();
                $partName = $topPart?->part_number ?: 'a part';

                $prompts[] = [
                    'type' => 'reorder_prompt',
                    'title' => 'Repeat award detected',
                    'description' => sprintf('Part %s has been awarded %d+ times. Consider a reorder or blanket PO.', $partName, (int) $topPart->awards_count),
                    'cta' => 'Plan a reorder',
                ];
            }

            $supplierAwards = RfqItemAward::query()
                ->select('supplier_id', DB::raw('COUNT(*) as awards_count'))
                ->whereIn('rfq_id', $linkedRfqIds)
                ->where('status', RfqItemAwardStatus::Awarded)
                ->groupBy('supplier_id')
                ->having('awards_count', '>=', 2)
                ->orderByDesc('awards_count')
                ->limit(3)
                ->get();

            if ($supplierAwards->isNotEmpty()) {
                $supplierIds = $supplierAwards->pluck('supplier_id')->filter()->all();
                $supplierNames = Supplier::query()
                    ->whereIn('id', $supplierIds)
                    ->pluck('name', 'id');

                $topSupplier = $supplierAwards->first();
                $supplierName = $supplierNames->get($topSupplier?->supplier_id, 'a supplier');

                $prompts[] = [
                    'type' => 'preferred_supplier',
                    'title' => 'Preferred supplier opportunity',
                    'description' => sprintf('%s has won %d+ awards for this twin. Consider marking as preferred.', $supplierName, (int) $topSupplier->awards_count),
                    'cta' => 'Add preferred supplier',
                ];
            }

            return $prompts;
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<int, array<string, mixed>>
     */
    private function findExpiringCertificates(array $extra): array
    {
        $documents = Arr::get($extra, 'linked_documents');

        if (! is_array($documents)) {
            return [];
        }

        $now = Carbon::now();
        $window = $now->copy()->addDays(30);

        return array_values(array_filter($documents, function ($document) use ($window): bool {
            if (! is_array($document)) {
                return false;
            }

            if (($document['source'] ?? null) !== 'supplier_certificate') {
                return false;
            }

            $expiresAt = $document['expires_at'] ?? null;

            if (! is_string($expiresAt) || trim($expiresAt) === '') {
                return false;
            }

            try {
                $date = Carbon::parse($expiresAt);
            } catch (\Throwable $exception) {
                return false;
            }

            return $date->greaterThanOrEqualTo($now) && $date->lessThanOrEqualTo($window);
        }));
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

            return $twin;
        });
    }

    /**
     * @return array<string, mixed>
     */
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
}
