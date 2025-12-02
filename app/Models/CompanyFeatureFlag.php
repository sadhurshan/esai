<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyFeatureFlag extends CompanyScopedModel
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
