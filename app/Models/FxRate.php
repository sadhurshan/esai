<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_code',
        'quote_code',
        'rate',
        'as_of',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'as_of' => 'date',
    ];

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_code', 'code');
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_code', 'code');
    }
}
