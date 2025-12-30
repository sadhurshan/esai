<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiWorkflowStep extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    public const APPROVAL_STATES = [
        self::APPROVAL_PENDING,
        self::APPROVAL_APPROVED,
        self::APPROVAL_REJECTED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'workflow_id',
        'step_index',
        'action_type',
        'input_json',
        'draft_json',
        'output_json',
        'approval_status',
        'approved_by',
        'approved_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'input_json' => 'array',
        'draft_json' => 'array',
        'output_json' => 'array',
        'approved_at' => 'datetime',
        'step_index' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AiWorkflow::class, 'workflow_id', 'workflow_id');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(AiApprovalRequest::class, 'workflow_step_id');
    }

    public function pendingApprovalRequest(): HasOne
    {
        return $this->hasOne(AiApprovalRequest::class, 'workflow_step_id')
            ->where('status', AiApprovalRequest::STATUS_PENDING)
            ->latestOfMany();
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::APPROVAL_REJECTED;
    }
}
