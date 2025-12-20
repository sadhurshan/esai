<?php

namespace App\Services\Ai\Workflow;

use App\Actions\Rfq\AwardLineItemsAction;
use App\Enums\RfqItemAwardStatus;
use App\Exceptions\AiWorkflowException;
use App\Models\AiWorkflowStep;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class QuoteComparisonDraftConverter
{
    public function __construct(
        private readonly AwardLineItemsAction $awardLineItems,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Apply shortlist and award updates once a quote comparison step is approved.
     */
    public function convert(AiWorkflowStep $step): array
    {
        $output = is_array($step->output_json) ? $step->output_json : [];
        $payload = is_array($output['payload'] ?? null) ? $output['payload'] : [];
        $companyId = (int) ($step->company_id ?? 0);

        if ($companyId <= 0 || $payload === []) {
            return $this->normalizeResponse($payload);
        }

        return CompanyContext::forCompany($companyId, function () use ($step, $payload): array {
            $rfq = $this->resolveRfq($step, $payload);

            if (! $rfq instanceof RFQ) {
                throw new AiWorkflowException('RFQ context missing for quote comparison.');
            }

            $rankings = $this->normalizeRankings($payload['rankings'] ?? []);

            if ($rankings === []) {
                return $this->normalizeResponse($payload, $rfq->id);
            }

            $quotes = $this->loadQuotes($rfq, $rankings);
            $shortlisted = $this->shortlistQuotes($quotes, $this->resolveApprover($step));
            $awardsCreated = $this->awardRecommendedSupplier($rfq, $quotes, $payload, $step);

            return [
                'rfq_id' => $rfq->id,
                'shortlisted_quote_ids' => $shortlisted,
                'awarded_supplier_id' => $awardsCreated['supplier_id'],
                'created_awards' => $awardsCreated['count'],
                'rankings' => $rankings,
                'recommendation' => $payload['recommendation'] ?? null,
            ];
        });
    }

    /**
     * @param  list<array{supplier_id:int}>  $rankings
     */
    private function loadQuotes(RFQ $rfq, array $rankings): Collection
    {
        $supplierIds = array_values(array_unique(array_column($rankings, 'supplier_id')));

        if ($supplierIds === []) {
            return collect();
        }

        $quotes = Quote::query()
            ->with(['items.rfqItem'])
            ->where('rfq_id', $rfq->id)
            ->whereIn('supplier_id', $supplierIds)
            ->get()
            ->keyBy(fn (Quote $quote) => (int) $quote->supplier_id);

        return collect($supplierIds)
            ->map(fn (int $supplierId) => $quotes->get($supplierId))
            ->filter();
    }

    /**
     * @return list<array{supplier_id:int,score:float,normalized_score:float,notes:?string}>
     */
    private function normalizeRankings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $supplierId = $this->normalizeInt($row['supplier_id'] ?? null);

            if ($supplierId === null) {
                continue;
            }

            $normalized[] = [
                'supplier_id' => $supplierId,
                'score' => (float) ($row['score'] ?? 0),
                'normalized_score' => (float) ($row['normalized_score'] ?? 0),
                'notes' => $row['notes'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<int>
     */
    private function shortlistQuotes(Collection $quotes, ?User $approver): array
    {
        $userId = $approver?->id;
        $now = now();
        $shortlisted = [];

        foreach ($quotes as $quote) {
            if (! $quote instanceof Quote) {
                continue;
            }

            $before = Arr::only($quote->getOriginal(), ['shortlisted_at', 'shortlisted_by']);
            $quote->shortlisted_at = $quote->shortlisted_at ?? $now;
            $quote->shortlisted_by = $quote->shortlisted_by ?? $userId;

            if ($quote->isDirty(['shortlisted_at', 'shortlisted_by'])) {
                $quote->save();

                $this->auditLogger->updated($quote, $before, [
                    'shortlisted_at' => $quote->shortlisted_at,
                    'shortlisted_by' => $quote->shortlisted_by,
                ]);
            }

            $shortlisted[] = (int) $quote->id;
        }

        return $shortlisted;
    }

    /**
     * @return array{count:int,supplier_id:?int}
     */
    private function awardRecommendedSupplier(RFQ $rfq, Collection $quotes, array $payload, AiWorkflowStep $step): array
    {
        $supplierId = $this->normalizeInt($payload['recommendation'] ?? null)
            ?? $quotes->first()?->supplier_id;

        if ($supplierId === null) {
            return ['count' => 0, 'supplier_id' => null];
        }

        /** @var Quote|null $quote */
        $quote = $quotes->firstWhere('supplier_id', (int) $supplierId);

        if (! $quote instanceof Quote) {
            return ['count' => 0, 'supplier_id' => null];
        }

        $approver = $this->resolveApprover($step);

        if (! $approver instanceof User) {
            return ['count' => 0, 'supplier_id' => null];
        }

        $quote->loadMissing('items.rfqItem');

        $alreadyAwardedIds = RfqItemAward::query()
            ->where('rfq_id', $rfq->id)
            ->where('status', RfqItemAwardStatus::Awarded)
            ->pluck('rfq_item_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $awards = [];

        foreach ($quote->items as $item) {
            $rfqItemId = (int) ($item->rfq_item_id ?? 0);

            if ($rfqItemId <= 0 || in_array($rfqItemId, $alreadyAwardedIds, true)) {
                continue;
            }

            $awards[] = [
                'rfq_item_id' => $rfqItemId,
                'quote_item_id' => (int) $item->id,
                'awarded_qty' => (int) ($item->rfqItem?->quantity ?? 1),
            ];
        }

        if ($awards === []) {
            return ['count' => 0, 'supplier_id' => $supplierId];
        }

        $this->awardLineItems->execute($rfq, $awards, $approver, createPurchaseOrders: false);

        return ['count' => count($awards), 'supplier_id' => $supplierId];
    }

    private function resolveRfq(AiWorkflowStep $step, array $payload): ?RFQ
    {
        $rfqId = $this->normalizeInt($step->input_json['rfq_id'] ?? $payload['rfq_id'] ?? null);

        if ($rfqId === null) {
            return null;
        }

        return RFQ::query()->find($rfqId);
    }

    private function resolveApprover(AiWorkflowStep $step): ?User
    {
        $userId = $this->normalizeInt($step->approved_by ?? null);

        if ($userId === null) {
            return null;
        }

        return User::query()->find($userId);
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeResponse(array $payload, ?int $rfqId = null): array
    {
        return [
            'rfq_id' => $rfqId,
            'shortlisted_quote_ids' => [],
            'awarded_supplier_id' => $this->normalizeInt($payload['recommendation'] ?? null),
            'created_awards' => 0,
            'rankings' => is_array($payload['rankings'] ?? null) ? $payload['rankings'] : [],
            'recommendation' => $payload['recommendation'] ?? null,
        ];
    }
}
