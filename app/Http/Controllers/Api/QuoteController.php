<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quote\SubmitQuoteAction;
use App\Exceptions\QuoteActionException;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\Quote\WithdrawQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Services\QuoteRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuoteController extends ApiController
{
    public function __construct(
        private readonly SubmitQuoteAction $submitQuoteAction,
        private readonly QuoteRevisionService $quoteRevisionService
    ) {}

    public function index(RFQ $rfq, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->company_id !== $rfq->company_id, 403);

        $paginator = $rfq
            ->quotes()
            ->with(['supplier', 'items', 'documents'])
            ->orderBy($request->query('sort', 'created_at'), $this->sortDirection($request))
            ->paginate($this->perPage($request))
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, QuoteResource::class);

        return $this->ok([
            'items' => $items,
            'meta' => $meta,
        ]);
    }

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $rfq = $request->rfq();

        $user = $request->user();
        abort_if($user === null, 403);

        $payload = $request->validated();

        Gate::authorize('submit', [Quote::class, $rfq, (int) $payload['supplier_id']]);

        $supplier = Supplier::query()
            ->with('company')
            ->findOrFail((int) $payload['supplier_id']);

        $quote = $this->submitQuoteAction->execute([
            'company_id' => $rfq->company_id,
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'submitted_by' => $user->id,
            'currency' => $payload['currency'],
            'unit_price' => $payload['unit_price'],
            'min_order_qty' => $payload['min_order_qty'] ?? null,
            'lead_time_days' => $payload['lead_time_days'],
            'note' => $payload['note'] ?? null,
            'items' => array_map(static fn (array $item) => [
                'rfq_item_id' => (int) $item['rfq_item_id'],
                'unit_price' => $item['unit_price'],
                'lead_time_days' => $item['lead_time_days'],
                'note' => $item['note'] ?? null,
            ], $payload['items']),
        ], $request->file('attachment'));

        $quote->load(['supplier', 'items', 'documents']);

        return $this->ok((new QuoteResource($quote))->toArray($request), 'Quote submitted')->setStatusCode(201);
    }

    public function withdraw(WithdrawQuoteRequest $request, RFQ $rfq, Quote $quote): JsonResponse
    {
        if ((int) $quote->rfq_id !== (int) $rfq->id) {
            return $this->fail('Quote not found.', 404);
        }

        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $quote->loadMissing(['supplier', 'company', 'rfq', 'items', 'documents', 'revisions.document']);

        if ($quote->company !== null) {
            $quote->company->loadMissing('plan');
        }

        Gate::authorize('withdraw', $quote);

        if (! $this->planAllowsRevisions($quote)) {
            return $this->fail('Upgrade required', 402, [
                'code' => 'quote_revisions_disabled',
                'upgrade_url' => url('/pricing'),
            ]);
        }

        $payload = $request->payload();

        try {
            $updatedQuote = $this->quoteRevisionService->withdrawQuote(
                $quote,
                $user,
                $payload['reason']
            );
        } catch (QuoteActionException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus());
        }

        $updatedQuote->loadMissing(['supplier', 'items', 'documents', 'revisions.document']);

        return $this->ok(
            (new QuoteResource($updatedQuote))->toArray($request),
            'Quote withdrawn'
        );
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
