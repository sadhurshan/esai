<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiEvent extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'company_id',
        'user_id',
        'feature',
        'entity_type',
        'entity_id',
        'request_json',
        'response_json',
        'latency_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'request_json' => 'array',
        'response_json' => 'array',
        'latency_ms' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(null, 'entity_type', 'entity_id');
    }
}
