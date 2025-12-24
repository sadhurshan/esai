<?php

namespace App\Services\Ai\Workflow;

use App\Actions\Rfq\AwardLineItemsAction;
use App\Exceptions\AiWorkflowException;
use App\Models\AiWorkflowStep;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\User;
use App\Support\CompanyContext;

class AwardQuoteDraftConverter
{
    public function __construct(private readonly AwardLineItemsAction $awardLineItems)
    {
    }

    /**
     * Promote an approved award quote draft into real RFQ awards.
     */
    public function convert(AiWorkflowStep $step): array
    {
        $output = is_array($step->output_json) ? $step->output_json : [];
        $payload = is_array($output['payload'] ?? null) ? $output['payload'] : [];
        $companyId = (int) ($step->company_id ?? 0);

        if ($companyId <= 0 || $payload === []) {
            return $this->normalizeResponse(null, null, null, 0);
        }

        return CompanyContext::forCompany($companyId, function () use ($step, $payload): array {
            $rfq = $this->resolveRfq($step, $payload);
            $quote = $this->resolveQuote($rfq, $payload);
            $approver = $this->resolveApprover($step);

            if (! $rfq instanceof RFQ) {
                throw new AiWorkflowException('RFQ context missing for award quote step.');
            }

            if (! $quote instanceof Quote) {
                throw new AiWorkflowException('Quote context missing for award quote step.');
            }

            if (! $approver instanceof User) {
                throw new AiWorkflowException('Approval context missing for award quote step.');
            }

            $awards = $this->buildAwardsFromQuote($quote, $payload);

            if ($awards === []) {
                return $this->normalizeResponse($rfq, $quote, $approver, 0);
            }

            $this->awardLineItems->execute($rfq, $awards, $approver, createPurchaseOrders: false);

            return $this->normalizeResponse($rfq, $quote, $approver, count($awards));
        });
    }

    private function resolveRfq(AiWorkflowStep $step, array $payload): ?RFQ
    {
        $rfqId = $this->normalizeInt($step->input_json['rfq_id'] ?? $payload['rfq_id'] ?? null);

        return $rfqId !== null ? RFQ::query()->find($rfqId) : null;
    }

    private function resolveQuote(?RFQ $rfq, array $payload): ?Quote
    {
        $quoteId = $this->normalizeInt($payload['selected_quote_id'] ?? $payload['quote_id'] ?? null);

        if ($quoteId === null) {
            return null;
        }

        $query = Quote::query()->with(['items.rfqItem']);

        if ($rfq instanceof RFQ) {
            $query->where('rfq_id', $rfq->id);
        }

        return $query->find($quoteId);
    }

    private function resolveApprover(AiWorkflowStep $step): ?User
    {
        $userId = $this->normalizeInt($step->approved_by ?? null);

        return $userId !== null ? User::query()->find($userId) : null;
    }

    /**
     * @return array<int, array{rfq_item_id:int,quote_item_id:int,awarded_qty:int}>
     */
    private function buildAwardsFromQuote(Quote $quote, array $payload): array
    {
        $quote->loadMissing(['items.rfqItem']);

        $awards = [];
        $defaultQty = $this->normalizeInt($payload['awarded_qty'] ?? null);

        foreach ($quote->items as $item) {
            $rfqItemId = (int) ($item->rfq_item_id ?? 0);

            if ($rfqItemId <= 0) {
                continue;
            }

            $awards[] = [
                'rfq_item_id' => $rfqItemId,
                'quote_item_id' => (int) $item->id,
                'awarded_qty' => $defaultQty ?? (int) ($item->rfqItem?->quantity ?? 1),
            ];
        }

        return $awards;
    }

    private function normalizeResponse(?RFQ $rfq, ?Quote $quote, ?User $approver, int $awards): array
    {
        return [
            'rfq_id' => $rfq?->id,
            'quote_id' => $quote?->id,
            'supplier_id' => $quote?->supplier_id,
            'awarded_by' => $approver?->id,
            'created_awards' => $awards,
        ];
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
    }
}
