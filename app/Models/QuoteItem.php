<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuoteItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'quote_id',
        'rfq_item_id',
        'unit_price',
        'lead_time_days',
        'note',
        'status',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
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
}
