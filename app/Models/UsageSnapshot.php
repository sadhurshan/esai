<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsageSnapshot extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'date',
        'rfqs_count',
        'quotes_count',
        'pos_count',
        'storage_used_mb',
    ];

    protected $casts = [
        'date' => 'date',
        'rfqs_count' => 'integer',
        'quotes_count' => 'integer',
        'pos_count' => 'integer',
        'storage_used_mb' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
