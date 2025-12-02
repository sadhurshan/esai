<?php

namespace App\Models;

use App\Enums\RateLimitScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateLimit extends CompanyScopedModel
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'window_seconds',
        'max_requests',
        'scope',
        'active',
    ];

    protected $casts = [
        'window_seconds' => 'integer',
        'max_requests' => 'integer',
        'active' => 'boolean',
        'scope' => RateLimitScope::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
