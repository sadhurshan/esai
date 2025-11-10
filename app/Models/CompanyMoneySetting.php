<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyMoneySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'base_currency',
        'pricing_currency',
        'fx_source',
        'price_round_rule',
        'tax_regime',
        'defaults_meta',
    ];

    protected $casts = [
        'defaults_meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'code');
    }

    public function pricingCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'pricing_currency', 'code');
    }
}
