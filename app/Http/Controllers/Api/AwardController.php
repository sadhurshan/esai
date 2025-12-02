<?php

namespace App\Http\Controllers\Api;

use App\Actions\PurchaseOrder\CreatePurchaseOrderFromQuoteAction;
use App\Http\Requests\AwardQuoteRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use Illuminate\Http\JsonResponse;

class AwardController extends ApiController
{
    public function __construct(private readonly CreatePurchaseOrderFromQuoteAction $createPurchaseOrderFromQuoteAction) {}

    public function store(RFQ $rfq, AwardQuoteRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Active company context required.', 422, [
                'code' => 'company_context_missing',
            ]);
        }

        if ($user->company_id === null) {
            $user->company_id = $companyId;
        }

        if ((int) $rfq->company_id !== $companyId) {
            return $this->fail('RFQ not found for this company.', 404);
        }

        $validated = $request->validated();

        /** @var Quote|null $quote */
        $quote = Quote::with(['items', 'supplier'])
            ->where('rfq_id', $rfq->id)
            ->where('id', $validated['quote_id'])
            ->first();

        if ($quote === null) {
            return $this->fail('Quote not found for this RFQ.', 404);
        }

        $existing = PurchaseOrder::with(['lines', 'rfq', 'quote.supplier'])
            ->where('quote_id', $quote->id)
            ->first();

        if ($existing !== null) {
            return $this->ok((new PurchaseOrderResource($existing))->toArray($request), 'Purchase order already exists');
        }

        $po = $this->createPurchaseOrderFromQuoteAction->execute(
            $quote,
            $quote->items->pluck('id')->all()
        )->load(['lines', 'rfq', 'quote.supplier']);

        return $this->ok((new PurchaseOrderResource($po))->toArray($request), 'Purchase order drafted')->setStatusCode(201);
    }
}
