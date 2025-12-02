<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quote\SubmitQuoteAction;
use App\Exceptions\QuoteActionException;
use App\Exceptions\RfqResponseWindowException;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\Quote\WithdrawQuoteRequest;
use App\Http\Resources\QuoteComparisonResource;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Services\QuoteComparisonService;
use App\Services\QuoteDraftService;
use App\Services\QuoteRevisionService;
use App\Support\RfqResponseWindowGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QuoteController extends ApiController
{
    public function __construct(
        private readonly SubmitQuoteAction $submitQuoteAction,
        private readonly QuoteRevisionService $quoteRevisionService,
        private readonly QuoteDraftService $quoteDraftService,
        private readonly QuoteComparisonService $quoteComparisonService,
        private readonly RfqResponseWindowGuard $rfqResponseWindowGuard,
    ) {}

    public function index(RFQ $rfq, Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        if ((int) $rfq->company_id !== $companyId) {
            return $this->fail('RFQ not found.', 404);
        }

        Gate::forUser($user)->authorize('view', $rfq);

        $direction = $this->sortDirection($request);
        $sortColumn = $request->query('sort', 'created_at');

        $paginator = $rfq
            ->quotes()
            ->with(['supplier', 'items.taxes.taxCode', 'items.rfqItem', 'documents'])
            ->orderBy($sortColumn, $direction)
            ->orderBy('quotes.id', $direction)
            ->cursorPaginate($this->perPage($request));

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, QuoteResource::class);

        return $this->ok([
            'items' => $items,
        ], null, $meta);
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $quote->loadMissing([
            'supplier.company.plan',
            'company.plan',
            'items.taxes.taxCode',
            'items.rfqItem',
            'documents',
            'revisions.document',
        ]);

        Gate::authorize('view', $quote);

        return $this->ok((new QuoteResource($quote))->toArray($request));
    }

    public function store(RFQ $rfq, StoreQuoteRequest $request): JsonResponse
    {
        return $this->persistQuote($rfq, $request);
    }

    public function storeStandalone(StoreQuoteRequest $request): JsonResponse
    {
        return $this->persistQuote($request->rfq(), $request);
    }

    public function submit(Request $request, RFQ $rfq, Quote $quote): JsonResponse
    {
        if ((int) $quote->rfq_id !== (int) $rfq->id) {
            return $this->fail('Quote not found for this RFQ.', 404);
        }

        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($quote->status !== 'draft') {
            return $this->fail('Only draft quotes can be submitted.', 422);
        }

        Gate::authorize('revise', $quote);

        $guardResponse = $this->guardRfqForResponses($rfq, 'submit quotes');

        if ($guardResponse instanceof JsonResponse) {
            return $guardResponse;
        }

        try {
            $updatedQuote = $this->quoteDraftService->submitDraft($quote, $user->id);
        } catch (ValidationException $exception) {
            return $this->fail($exception->getMessage(), 422, $exception->errors());
        }

        return $this->ok((new QuoteResource($updatedQuote))->toArray($request), 'Quote submitted');
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
                'upgrade_url' => url('/app/setup/plan').'?mode=change',
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

    public function compare(RFQ $rfq, Request $request): JsonResponse
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
            return $this->fail('RFQ not found.', 404);
        }

        Gate::authorize('view', $rfq);

        $matrix = $this->quoteComparisonService->build($rfq);

        $items = $matrix
            ->map(fn (array $row) => (new QuoteComparisonResource($row))->toArray($request))
            ->all();

        return $this->ok([
            'items' => $items,
        ]);
    }

    private function persistQuote(RFQ $rfq, StoreQuoteRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user] = $context;

        $payload = $request->validated();
        $status = 'draft';

        Gate::forUser($user)->authorize('submit', [Quote::class, $rfq, (int) $payload['supplier_id']]);

        $guardResponse = $this->guardRfqForResponses($rfq, 'create quote drafts');

        if ($guardResponse instanceof JsonResponse) {
            return $guardResponse;
        }

        $supplier = Supplier::query()
            ->with('company')
            ->findOrFail((int) $payload['supplier_id']);

        $items = array_map(static function (array $item): array {
            return [
                'rfq_item_id' => (int) $item['rfq_item_id'],
                'unit_price' => array_key_exists('unit_price', $item) ? $item['unit_price'] : null,
                'unit_price_minor' => array_key_exists('unit_price_minor', $item) ? $item['unit_price_minor'] : null,
                'currency' => $item['currency'] ?? null,
                'lead_time_days' => (int) $item['lead_time_days'],
                'tax_code_ids' => array_map('intval', $item['tax_code_ids'] ?? []),
                'note' => $item['note'] ?? null,
                'status' => $item['status'] ?? null,
            ];
        }, $payload['items']);

        $quote = $this->submitQuoteAction->execute([
            'company_id' => $rfq->company_id,
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'submitted_by' => null,
            'currency' => $payload['currency'],
            'unit_price' => $payload['unit_price'],
            'min_order_qty' => $payload['min_order_qty'] ?? null,
            'lead_time_days' => $payload['lead_time_days'],
            'notes' => $payload['notes'] ?? $payload['note'] ?? null,
            'status' => $status,
            'items' => $items,
        ], $request->file('attachment'));

        $quote->load(['supplier', 'items.taxes.taxCode', 'items.rfqItem', 'documents']);

        if ($quote->supplier?->company instanceof \App\Models\Company) {
            $quote->supplier->company->loadMissing('plan');
        }

        return $this->ok((new QuoteResource($quote))->toArray($request), 'Quote draft created')->setStatusCode(201);
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

    private function guardRfqForResponses(RFQ $rfq, string $action): ?JsonResponse
    {
        try {
            $this->rfqResponseWindowGuard->ensureOpenForResponses($rfq, $action);
        } catch (RfqResponseWindowException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus(), $exception->getErrors());
        }

        return null;
    }
}
