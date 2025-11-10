<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QuoteActionException;
use App\Http\Requests\Quote\StoreQuoteRevisionRequest;
use App\Http\Resources\QuoteRevisionResource;
use App\Models\Quote;
use App\Models\RFQ;
use App\Services\QuoteRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuoteRevisionController extends ApiController
{
    public function __construct(private readonly QuoteRevisionService $quoteRevisionService) {}

    public function index(Request $request, RFQ $rfq, Quote $quote): JsonResponse
    {
        if ((int) $quote->rfq_id !== (int) $rfq->id) {
            return $this->fail('Quote not found.', 404);
        }

        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $quote->loadMissing(['revisions.document', 'supplier', 'company']);

        Gate::authorize('viewRevisions', $quote);

        $items = $quote->revisions
            ->sortBy('revision_no')
            ->map(fn ($revision) => (new QuoteRevisionResource($revision))->toArray($request))
            ->values()
            ->all();

        return $this->ok([
            'items' => $items,
        ]);
    }

    public function store(StoreQuoteRevisionRequest $request, RFQ $rfq, Quote $quote): JsonResponse
    {
        if ((int) $quote->rfq_id !== (int) $rfq->id) {
            return $this->fail('Quote not found.', 404);
        }

        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $quote->loadMissing(['supplier', 'company', 'rfq']);

        if ($quote->company !== null) {
            $quote->company->loadMissing('plan');
        }

        Gate::authorize('revise', $quote);

        if (! $this->planAllowsRevisions($quote)) {
            return $this->fail('Upgrade required', 402, [
                'code' => 'quote_revisions_disabled',
                'upgrade_url' => url('/pricing'),
            ]);
        }

        try {
            $revision = $this->quoteRevisionService->submitRevision(
                $quote,
                $request->payload(),
                $request->file('attachment'),
                $user
            );
        } catch (QuoteActionException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus());
        }

        return $this->ok(
            (new QuoteRevisionResource($revision))->toArray($request),
            'Quote revision submitted'
        )->setStatusCode(201);
    }

    private function planAllowsRevisions(Quote $quote): bool
    {
        $company = $quote->company;

        if ($company === null) {
            return false;
        }

        $plan = $company->plan;

        if ($plan === null) {
            return false;
        }

        return (bool) $plan->quote_revisions_enabled;
    }
}
