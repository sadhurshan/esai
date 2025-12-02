<?php

namespace App\Http\Controllers\Api;
use App\Support\CompanyContext;

use App\Actions\Rfq\AwardLineItemsAction;
use App\Actions\Rfq\DeleteRfqItemAwardAction;
use App\Http\Requests\Award\CreateAwardsRequest;
use App\Http\Resources\RfqItemAwardResource;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class AwardLineController extends ApiController
{
    public function __construct(
        private readonly AwardLineItemsAction $awardLineItemsAction,
        private readonly DeleteRfqItemAwardAction $deleteRfqItemAwardAction,
    ) {
    }

    public function store(CreateAwardsRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;
        $validated = $request->validated();
        $rfqId = (int) $validated['rfq_id'];

        /** @var RFQ|null $rfq */
        $rfq = RFQ::query()
            ->where('company_id', $companyId)
            ->find($rfqId);

        if (! $rfq instanceof RFQ) {
            return $this->fail('RFQ not found.', 404);
        }

        Gate::forUser($user)->authorize('awardLines', $rfq);

        $awardsPayload = collect($validated['items'] ?? [])
            ->map(fn (array $item): array => [
                'rfq_item_id' => (int) $item['rfq_item_id'],
                'quote_item_id' => (int) $item['quote_item_id'],
                'awarded_qty' => Arr::get($item, 'awarded_qty'),
            ])
            ->values()
            ->all();

        $this->awardLineItemsAction->execute($rfq, $awardsPayload, $user, false);

            CompanyContext::bypass(fn () => $rfq->load(['awards.supplier']));

        return $this->ok([
            'awards' => RfqItemAwardResource::collection($rfq->awards)->resolve(),
        ], 'RFQ lines awarded');
    }

    public function destroy(Request $request, RfqItemAward $award): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ((int) $award->company_id !== $companyId) {
            return $this->fail('Award not found.', 404);
        }

        $award->loadMissing('rfq');
        $rfq = $award->rfq;

        if (! $rfq instanceof RFQ || (int) $rfq->company_id !== $companyId) {
            return $this->fail('RFQ not found.', 404);
        }

        Gate::forUser($user)->authorize('awardLines', $rfq);

        $this->deleteRfqItemAwardAction->execute($award);

            CompanyContext::bypass(fn () => $rfq->load(['awards.supplier']));

        return $this->ok([
            'awards' => RfqItemAwardResource::collection($rfq->awards)->resolve(),
        ], 'Award deleted');
    }
}
