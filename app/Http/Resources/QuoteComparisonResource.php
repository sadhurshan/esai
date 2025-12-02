<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @extends JsonResource<array{quote: Quote, scores: array{price: float, lead_time: float, rating: float, composite: float, rank: int}} */
class QuoteComparisonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Quote $quote */
        $quote = $this->resource['quote'];
        $scores = $this->resource['scores'];

        $quotePayload = (new QuoteResource($quote))->toArray($request);

        return [
            'quote_id' => (int) $quote->getKey(),
            'rfq_id' => (int) $quote->rfq_id,
            'supplier' => $quotePayload['supplier'],
            'currency' => $quotePayload['currency'],
            'total_price_minor' => $quotePayload['total_price_minor'],
            'lead_time_days' => $quote->lead_time_days,
            'status' => $quote->status,
            'attachments_count' => $quotePayload['attachments_count'],
            'submitted_at' => $quotePayload['submitted_at'],
            'scores' => $scores,
            'quote' => $quotePayload,
        ];
    }
}
