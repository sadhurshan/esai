<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RfqResponseWindowException;
use App\Http\Requests\Quote\StoreQuoteLineRequest;
use App\Http\Requests\Quote\UpdateQuoteLineRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\QuoteDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QuoteLineController extends ApiController
{
    public function __construct(private readonly QuoteDraftService $quoteDraftService) {}

    public function store(StoreQuoteLineRequest $request, Quote $quote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        Gate::authorize('revise', $quote);

        try {
            $updatedQuote = $this->quoteDraftService->addLine($quote, $request->payload());
        } catch (ValidationException $exception) {
            return $this->fail($exception->getMessage(), 422, $exception->errors());
        } catch (RfqResponseWindowException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus(), $exception->getErrors());
        }

        return $this->ok(
            (new QuoteResource($updatedQuote))->toArray($request),
            'Quote line added'
        )->setStatusCode(201);
    }

    public function update(UpdateQuoteLineRequest $request, Quote $quote, QuoteItem $quoteItem): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $quoteItem->quote_id !== (int) $quote->id) {
            return $this->fail('Quote line not found.', 404);
        }

        Gate::authorize('revise', $quote);

        try {
            $updatedQuote = $this->quoteDraftService->updateLine($quote, $quoteItem, $request->payload());
        } catch (ValidationException $exception) {
            return $this->fail($exception->getMessage(), 422, $exception->errors());
        } catch (RfqResponseWindowException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus(), $exception->getErrors());
        }

        return $this->ok(
            (new QuoteResource($updatedQuote))->toArray($request),
            'Quote line updated'
        );
    }

    public function destroy(Request $request, Quote $quote, QuoteItem $quoteItem): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $quoteItem->quote_id !== (int) $quote->id) {
            return $this->fail('Quote line not found.', 404);
        }

        Gate::authorize('revise', $quote);

        try {
            $updatedQuote = $this->quoteDraftService->deleteLine($quote, $quoteItem);
        } catch (ValidationException $exception) {
            return $this->fail($exception->getMessage(), 422, $exception->errors());
        } catch (RfqResponseWindowException $exception) {
            return $this->fail($exception->getMessage(), $exception->getStatus(), $exception->getErrors());
        }

        return $this->ok(
            (new QuoteResource($updatedQuote))->toArray($request),
            'Quote line removed'
        );
    }
}
