<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quote\RecalculateQuoteTotalsAction;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteTotalsController extends ApiController
{
    public function __construct(private readonly RecalculateQuoteTotalsAction $recalculateQuoteTotals)
    {
    }

    public function recalculate(Request $request, Quote $quote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($user->company_id === null || (int) $user->company_id !== (int) $quote->company_id) {
            return $this->fail('Quote not found for this company.', 404);
        }

        $quote = $this->recalculateQuoteTotals->execute($quote);

        $quote->loadMissing(['supplier', 'items.taxes.taxCode', 'items.rfqItem', 'documents', 'revisions']);

        return $this->ok((new QuoteResource($quote))->toArray($request), 'Quote totals recalculated.');
    }
}
