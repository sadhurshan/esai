<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PoChangeOrder;
use App\Models\PurchaseOrder;
use App\Models\RFQ;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DelegationService $delegations,
        private readonly NotificationService $notifications
    ) {
    }

    public function triggerApproval(Model $entity): ?Approval
    {
        $company = $this->resolveCompany($entity);
        $company->loadMissing('plan');

        if ($company->plan === null || ! $company->plan->approvals_enabled) {
            throw ValidationException::withMessages([
                'approvals' => ['Upgrade required to use approvals.'],
            ]);
        }

        $targetType = $this->resolveTargetType($entity);
        $amount = $this->resolveAmount($entity);

        $rule = $company->approvalRules()
            ->active()
            ->where('target_type', $targetType)
            ->where('threshold_min', '<=', $amount)
            ->where(function ($query) use ($amount): void {
                $query->whereNull('threshold_max')
                    ->orWhere('threshold_max', '>=', $amount);
            })
            ->orderByDesc('threshold_min')
            ->first();

        if (! $rule instanceof ApprovalRule) {
            return null;
        }

        if ($company->plan->approval_levels_limit > 0 && $rule->levelsCount() > $company->plan->approval_levels_limit) {
            throw ValidationException::withMessages([
                'levels_json' => ['Current plan cannot support the configured approval levels.'],
            ]);
        }

        $levelConfig = collect($rule->orderedLevels())
            ->first(function (array $level) use ($amount): bool {
                if (isset($level['max_amount']) && (float) $level['max_amount'] > 0) {
                    return $amount <= (float) $level['max_amount'];
                }

                return true;
            });

        if ($levelConfig === null) {
            return null;
        }

        $approver = $this->resolveApproverForLevel($company, $levelConfig, $amount);
        $delegatedTo = $approver !== null
            ? $this->delegations->resolveActiveDelegate($company->id, $approver->id, Carbon::today())
            : null;
        $actingApprover = $delegatedTo instanceof \App\Models\Delegation
            ? $delegatedTo->delegate
            : $approver;

        $approval = null;

        DB::transaction(function () use ($company, $rule, $entity, $targetType, $levelConfig, &$approval): void {
            $approval = Approval::create([
                'company_id' => $company->id,
                'approval_rule_id' => $rule->id,
                'target_type' => $targetType,
                'target_id' => $entity->getKey(),
                'level_no' => (int) ($levelConfig['level_no'] ?? 1),
                'status' => ApprovalStatus::Pending,
            ]);

            $this->auditLogger->created($approval, [
                'target_type' => $targetType,
                'target_id' => $entity->getKey(),
                'level_no' => $approval->level_no,
            ]);
        });

        if ($actingApprover instanceof User) {
            $this->notifyNextApprover($actingApprover, $entity, $targetType, $approval);
        }

        return $approval;
    }

    public function processApproval(Approval $approval, string $decision, ?string $comment, ?User $actingUser = null): Approval
    {
        if (! $approval->isPending()) {
            throw ValidationException::withMessages([
                'approval' => ['Approval is no longer pending.'],
            ]);
        }

        $decision = strtolower($decision);

        if (! in_array($decision, ['approve', 'reject', 'skip'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Invalid decision provided.'],
            ]);
        }

        $entity = $this->loadEntity($approval);
        $company = $this->resolveCompany($entity);

        $expectedApproverId = $approval->expectedApproverUserId($this->resolveAmount($entity));
        $delegate = null;

        if ($expectedApproverId !== null) {
            $delegate = $this->delegations->resolveActiveDelegate($company->id, $expectedApproverId, Carbon::today());
        }

        $allowedActorIds = array_filter([
            $expectedApproverId,
            $delegate?->delegate_user_id,
        ]);

        if ($actingUser !== null && ! in_array($actingUser->id, $allowedActorIds, true)) {
            throw ValidationException::withMessages([
                'decision' => ['You are not authorized to act on this approval.'],
            ]);
        }

        $status = match ($decision) {
            'approve' => ApprovalStatus::Approved,
            'reject' => ApprovalStatus::Rejected,
            default => ApprovalStatus::Skipped,
        };

        $before = $approval->getOriginal();

        $approval->status = $status;
        $approval->approved_by_id = $actingUser?->id ?? $expectedApproverId;
        $approval->approved_at = Carbon::now();
        $approval->comment = $comment;
        $approval->save();

        $this->auditLogger->updated($approval, $before, $approval->toArray());

        if ($status === ApprovalStatus::Rejected) {
            $this->applyOutcome($entity, $approval->target_type, 'rejected');
            $this->closeSiblingApprovals($approval);

            return $approval;
        }

        if ($status === ApprovalStatus::Skipped) {
            return $approval;
        }

        $nextLevelNo = $approval->level_no + 1;
        $rule = $approval->approvalRule()->first();

        if (! $rule instanceof ApprovalRule) {
            return $approval;
        }

        $nextLevel = collect($rule->orderedLevels())
            ->first(function (array $level) use ($nextLevelNo, $entity): bool {
                if ((int) ($level['level_no'] ?? 0) !== $nextLevelNo) {
                    return false;
                }

                $amount = $this->resolveAmount($entity);

                if (isset($level['max_amount']) && (float) $level['max_amount'] > 0) {
                    return $amount <= (float) $level['max_amount'];
                }

                return true;
            });

        if ($nextLevel === null) {
            $this->applyOutcome($entity, $approval->target_type, 'approved');

            return $approval;
        }

        $nextApprover = $this->resolveApproverForLevel($company, $nextLevel, $this->resolveAmount($entity));
        $delegate = $nextApprover !== null
            ? $this->delegations->resolveActiveDelegate($company->id, $nextApprover->id, Carbon::today())
            : null;
    $actualApprover = $delegate?->delegate ?? $nextApprover;

        $nextApproval = null;

        DB::transaction(function () use ($company, $rule, $entity, $nextLevel, $approval, &$nextApproval): void {
            $nextApproval = Approval::create([
                'company_id' => $company->id,
                'approval_rule_id' => $rule->id,
                'target_type' => $approval->target_type,
                'target_id' => $approval->target_id,
                'level_no' => (int) ($nextLevel['level_no'] ?? $approval->level_no + 1),
                'status' => ApprovalStatus::Pending,
            ]);

            $this->auditLogger->created($nextApproval, [
                'target_type' => $approval->target_type,
                'target_id' => $approval->target_id,
                'level_no' => $nextApproval->level_no,
            ]);
        });

        if ($actualApprover instanceof User) {
            $this->notifyNextApprover($actualApprover, $entity, $approval->target_type, $nextApproval);
        }

        return $approval;
    }

    private function resolveCompany(Model $entity): Company
    {
        if ($entity instanceof Company) {
            return $entity;
        }

        if (method_exists($entity, 'company') && $entity->relationLoaded('company')) {
            $company = $entity->getRelation('company');
            if ($company instanceof Company) {
                return $company;
            }
        }

        if (isset($entity->company) && $entity->company instanceof Company) {
            return $entity->company;
        }

        if (isset($entity->company_id)) {
            return Company::findOrFail($entity->company_id);
        }

        if ($entity instanceof PoChangeOrder) {
            $purchaseOrder = $entity->purchaseOrder()->with('company')->firstOrFail();

            return $purchaseOrder->company;
        }

        throw new \InvalidArgumentException('Unable to resolve company for approval workflow.');
    }

    private function resolveTargetType(Model $entity): string
    {
        return match ($entity::class) {
            RFQ::class => 'rfq',
            PurchaseOrder::class => 'purchase_order',
            PoChangeOrder::class => 'change_order',
            Invoice::class => 'invoice',
            default => 'ncr', // TODO: clarify target type mapping when NCR model is available.
        };
    }

    private function resolveAmount(Model $entity): float
    {
        if (isset($entity->total) && is_numeric($entity->total)) {
            return (float) $entity->total;
        }

        if ($entity instanceof PurchaseOrder) {
            $entity->loadMissing('lines');

            return $entity->lines->sum(function ($line) {
                $qty = (float) Arr::get($line, 'quantity', 0);
                $unitPrice = (float) Arr::get($line, 'unit_price', 0);

                return $qty * $unitPrice;
            });
        }

        if ($entity instanceof Invoice) {
            return (float) ($entity->total ?? 0);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $level
     */
    private function resolveApproverForLevel(Company $company, array $level, float $amount): ?User
    {
        if (isset($level['max_amount']) && (float) $level['max_amount'] > 0 && $amount > (float) $level['max_amount']) {
            return null;
        }

        if (! empty($level['approver_user_id'])) {
            return $company->users()
                ->where('id', (int) $level['approver_user_id'])
                ->first();
        }

        if (! empty($level['approver_role'])) {
            return $company->users()
                ->where('role', $level['approver_role'])
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    private function loadEntity(Approval $approval): Model
    {
        $modelClass = match ($approval->target_type) {
            'rfq' => RFQ::class,
            'purchase_order' => PurchaseOrder::class,
            'change_order' => PoChangeOrder::class,
            'invoice' => Invoice::class,
            default => null,
        };

        if ($modelClass === null || ! class_exists($modelClass)) {
            throw new \RuntimeException('Unsupported approval target type.');
        }

        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($approval->target_id);

        return $model;
    }

    private function applyOutcome(Model $entity, string $targetType, string $outcome): void
    {
        if ($targetType === 'purchase_order') {
            if ($outcome === 'approved') {
                $entity->status = 'confirmed';
            } else {
                // TODO: once purchase order status enum supports a rejected state, switch from cancelled.
                $entity->status = 'cancelled';
            }
        } elseif ($targetType === 'change_order') {
            $entity->status = $outcome === 'approved' ? 'approved' : 'rejected';
        } elseif ($targetType === 'invoice') {
            // TODO: align invoice status enum with approved flow; using pending/disputed until spec clarifies.
            $entity->status = $outcome === 'approved' ? 'pending' : 'disputed';
        } elseif ($targetType === 'rfq') {
            $entity->status = $outcome === 'approved' ? 'open' : 'cancelled';
        } else {
            // TODO: clarify NCR status mapping once the model is introduced.
        }

        $entity->save();

        $this->auditLogger->updated($entity, [], $entity->toArray());
    }

    private function closeSiblingApprovals(Approval $approval): void
    {
        Approval::query()
            ->where('company_id', $approval->company_id)
            ->where('target_type', $approval->target_type)
            ->where('target_id', $approval->target_id)
            ->where('status', ApprovalStatus::Pending)
            ->update([
                'status' => ApprovalStatus::Skipped->value,
                'updated_at' => Carbon::now(),
            ]);
    }

    private function notifyNextApprover(User $recipient, Model $entity, string $targetType, Approval $approval): void
    {
        $title = match ($targetType) {
            'purchase_order' => 'Purchase order approval required',
            'change_order' => 'Change order approval required',
            'invoice' => 'Invoice approval required',
            'rfq' => 'RFQ publication pending approval',
            default => 'Approval required',
        };

        $body = sprintf(
            'An approval task (level %d) is waiting for your decision.',
            $approval->level_no
        );

        $this->notifications->send(
            [$recipient],
            'approvals.pending',
            $title,
            $body,
            $entity::class,
            $entity->getKey(),
            [
                'level' => $approval->level_no,
                'target_type' => $targetType,
            ]
        );
    }
}
