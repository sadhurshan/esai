<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiModelMetric extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_model_metrics';

    protected $fillable = [
        'company_id',
        'feature',
        'entity_type',
        'entity_id',
        'metric_name',
        'metric_value',
        'window_start',
        'window_end',
        'notes',
    ];

    protected $casts = [
        'metric_value' => 'decimal:6',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'notes' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
