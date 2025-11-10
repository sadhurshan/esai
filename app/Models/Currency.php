<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'minor_unit',
        'symbol',
    ];

    protected $casts = [
        'minor_unit' => 'integer',
    ];

    public function baseRates(): HasMany
    {
        return $this->hasMany(FxRate::class, 'base_code', 'code');
    }

    public function quoteRates(): HasMany
    {
        return $this->hasMany(FxRate::class, 'quote_code', 'code');
    }
}
