<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LineTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'tax_code_id',
        'taxable_type',
        'taxable_id',
        'rate_percent',
        'amount_minor',
        'sequence',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:3',
        'amount_minor' => 'integer',
        'sequence' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }

    public function taxable(): MorphTo
    {
        return $this->morphTo();
    }
}
