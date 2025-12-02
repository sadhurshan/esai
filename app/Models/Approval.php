<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Approval extends CompanyScopedModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'approval_rule_id',
        'target_type',
        'target_id',
        'level_no',
        'status',
        'approved_by_id',
        'comment',
        'approved_at',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvalRule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [ApprovalStatus::Approved, ApprovalStatus::Rejected], true);
    }

    public function expectedApproverUserId(?float $amount = null): ?int
    {
        $rule = $this->approvalRule;

        if (! $rule instanceof ApprovalRule) {
            return null;
        }

        $level = $rule->levelConfig($this->level_no);

        if ($level === null) {
            return null;
        }

        if ($amount !== null && isset($level['max_amount']) && (float) $level['max_amount'] > 0 && $amount > (float) $level['max_amount']) {
            return null;
        }

        if (! empty($level['approver_user_id'])) {
            return (int) $level['approver_user_id'];
        }

        if (! empty($level['approver_role'])) {
            $company = $this->company;

            if (! $company instanceof Company) {
                return null;
            }

            return $company->users()
                ->where('role', $level['approver_role'])
                ->orderBy('id')
                ->value('id');
        }

        return null;
    }
}
