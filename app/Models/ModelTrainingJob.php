<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModelTrainingJob extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const FEATURE_FORECAST = 'forecast';
    public const FEATURE_RISK = 'risk';
    public const FEATURE_RAG = 'rag';
    public const FEATURE_ACTIONS = 'actions';
    public const FEATURE_WORKFLOWS = 'workflows';
    public const FEATURE_CHAT = 'chat';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const FEATURE_CLIENT_METHODS = [
        self::FEATURE_FORECAST => 'trainForecast',
        self::FEATURE_RISK => 'trainRisk',
        self::FEATURE_RAG => 'trainRag',
        self::FEATURE_ACTIONS => 'trainActions',
        self::FEATURE_WORKFLOWS => 'trainWorkflows',
        self::FEATURE_CHAT => null,
    ];

    public const FEATURES = [
        self::FEATURE_FORECAST,
        self::FEATURE_RISK,
        self::FEATURE_RAG,
        self::FEATURE_ACTIONS,
        self::FEATURE_WORKFLOWS,
        self::FEATURE_CHAT,
    ];

    protected $fillable = [
        'company_id',
        'feature',
        'status',
        'microservice_job_id',
        'parameters_json',
        'result_json',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'parameters_json' => 'array',
        'result_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * @return list<string>
     */
    public static function allowedFeatures(): array
    {
        return self::FEATURES;
    }

    public static function clientMethodForFeature(string $feature): ?string
    {
        $normalized = strtolower(trim($feature));

        return self::FEATURE_CLIENT_METHODS[$normalized] ?? null;
    }
}
