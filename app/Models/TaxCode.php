<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxCode extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'rate_percent',
        'is_compound',
        'active',
        'meta',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:3',
        'is_compound' => 'boolean',
        'active' => 'boolean',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lineTaxes(): HasMany
    {
        return $this->hasMany(LineTax::class);
    }
}
