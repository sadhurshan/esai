<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingObservation extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'rfq_id',
        'rfq_item_id',
        'quote_id',
        'quote_item_id',
        'revision_no',
        'process',
        'material',
        'finish',
        'region',
        'quantity',
        'currency',
        'unit_price_minor',
        'source_type',
        'observed_at',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'revision_no' => 'integer',
        'observed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
    }
}
