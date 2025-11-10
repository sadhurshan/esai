<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'rfq_id',
        'line_no',
        'part_name',
        'spec',
        'quantity',
        'uom',
        'target_price',
        'currency',
        'target_price_minor',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'target_price' => 'decimal:2',
        'target_price_minor' => 'integer',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RFQ::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(RfqItemAward::class);
    }
}
