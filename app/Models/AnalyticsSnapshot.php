<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnalyticsSnapshot extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_CYCLE_TIME = 'cycle_time';
    public const TYPE_OTIF = 'otif';
    public const TYPE_RESPONSE_RATE = 'response_rate';
    public const TYPE_SPEND = 'spend';
    public const TYPE_FORECAST_ACCURACY = 'forecast_accuracy';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_CYCLE_TIME,
        self::TYPE_OTIF,
        self::TYPE_RESPONSE_RATE,
        self::TYPE_SPEND,
        self::TYPE_FORECAST_ACCURACY,
    ];

    protected $fillable = [
        'company_id',
        'type',
        'period_start',
        'period_end',
        'value',
        'meta',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'value' => 'decimal:4',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
