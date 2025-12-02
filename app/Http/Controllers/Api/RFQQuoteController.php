<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\RFQQuoteStoreRequest;
use App\Http\Resources\RFQQuoteResource;
use App\Models\RFQ;
use App\Models\RFQQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RFQQuoteController extends ApiController
{
    public function index(string $rfqId, Request $request): JsonResponse
    {
        try {
            $rfq = RFQ::find($rfqId);

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            $paginator = RFQQuote::with('supplier')
                ->where('rfq_id', $rfq->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->cursorPaginate($this->perPage($request));

            ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, RFQQuoteResource::class);

            return $this->ok([
                'items' => $items,
            ], null, $meta);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }

    public function store(string $rfqId, RFQQuoteStoreRequest $request): JsonResponse
    {
        try {
            $rfq = RFQ::find($rfqId);

            if (! $rfq) {
                return $this->fail('Not found', 404);
            }

            $data = $request->validated();

            if ($request->hasFile('attachment')) {
                $data['attachment_path'] = $request->file('attachment')->store('attachments');
            }

            unset($data['attachment']);

            $data['submitted_at'] = now();
            $data['rfq_id'] = $rfq->id;

            $quote = RFQQuote::create($data);

            return $this->ok((new RFQQuoteResource($quote->load('supplier')))->toArray($request), 'RFQ quote created')->setStatusCode(201);
        } catch (\Throwable $throwable) {
            report($throwable);

            return $this->fail('Server error', 500);
        }
    }
}
