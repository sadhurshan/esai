<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiWorkflow extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ABORTED = 'aborted';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_REJECTED,
        self::STATUS_ABORTED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'workflow_id',
        'workflow_type',
        'status',
        'current_step',
        'steps_json',
        'last_event_time',
        'last_event_type',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'steps_json' => 'array',
        'last_event_time' => 'datetime',
        'current_step' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AiWorkflowStep::class, 'workflow_id', 'workflow_id')
            ->orderBy('step_index');
    }

    public function stepMetadata(int $stepIndex): ?array
    {
        $steps = $this->steps_json;

        if (! is_array($steps)) {
            return null;
        }

        $collection = $steps['steps'] ?? $steps;

        if (! is_array($collection)) {
            return null;
        }

        foreach ($collection as $step) {
            if ((int) ($step['step_index'] ?? -1) === $stepIndex) {
                return $step;
            }
        }

        return null;
    }

    public function stepName(int $stepIndex): ?string
    {
        $metadata = $this->stepMetadata($stepIndex);

        if ($metadata === null) {
            return null;
        }

        $name = $metadata['name'] ?? $metadata['action_type'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
