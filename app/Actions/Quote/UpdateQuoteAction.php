<?php

namespace App\Actions\Quote;

use App\Models\Quote;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateQuoteAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array{currency?: string|null, unit_price?: string|float|null, min_order_qty?: int|null, lead_time_days?: int|null, note?: string|null, status?: string|null} $attributes
     */
    public function execute(Quote $quote, array $attributes): Quote
    {
        return DB::transaction(function () use ($quote, $attributes): Quote {
            $before = $quote->getOriginal();

            $quote->fill(array_filter($attributes, fn ($value) => $value !== null));
            $quote->save();

            $this->auditLogger->updated($quote, $before, $quote->getChanges());

            return $quote->fresh('items');
        });
    }
}
