<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quote\SubmitQuoteAction;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuoteController extends ApiController
{
    public function __construct(private readonly SubmitQuoteAction $submitQuoteAction) {}

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
}
