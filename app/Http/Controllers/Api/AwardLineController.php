<?php

namespace App\Http\Controllers\Api;

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
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $validated = $request->validated();

        /** @var RFQ $rfq */
        $rfq = RFQ::with(['awards.supplier'])
            ->findOrFail((int) $validated['rfq_id']);

        $awardsPayload = collect($validated['items'] ?? [])
            ->map(fn (array $item): array => [
                'rfq_item_id' => (int) $item['rfq_item_id'],
                'quote_item_id' => (int) $item['quote_item_id'],
                'awarded_qty' => Arr::get($item, 'awarded_qty'),
            ])
            ->values()
            ->all();

        $this->awardLineItemsAction->execute($rfq, $awardsPayload, $user, false);

        $rfq->load(['awards.supplier']);

        return $this->ok([
            'awards' => RfqItemAwardResource::collection($rfq->awards)->resolve(),
        ], 'RFQ lines awarded');
    }

    public function destroy(Request $request, RfqItemAward $award): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($user->company_id === null || (int) $user->company_id !== (int) $award->company_id) {
            abort(404);
        }

        $award->loadMissing('rfq');

        Gate::forUser($user)->authorize('awardLines', $award->rfq);

        $rfqId = $award->rfq_id;

        $this->deleteRfqItemAwardAction->execute($award);

        $rfq = RFQ::with(['awards.supplier'])->findOrFail($rfqId);

        return $this->ok([
            'awards' => RfqItemAwardResource::collection($rfq->awards)->resolve(),
        ], 'Award deleted');
    }
}
