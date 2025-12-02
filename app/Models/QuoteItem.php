<?php

namespace App\Models;

use App\Models\LineTax;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteItem extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'quote_id',
        'company_id',
        'rfq_item_id',
        'unit_price',
        'currency',
        'unit_price_minor',
        'lead_time_days',
        'note',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'unit_price_minor' => 'integer',
        'lead_time_days' => 'integer',
        'status' => 'string',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }

    public function award(): HasOne
    {
        return $this->hasOne(RfqItemAward::class);
    }

    public function taxes(): MorphMany
    {
        return $this->morphMany(LineTax::class, 'taxable')->orderBy('sequence');
    }
}
