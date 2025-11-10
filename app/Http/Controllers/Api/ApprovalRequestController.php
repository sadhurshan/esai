<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Approval\ApproveRequestActionRequest;
use App\Http\Resources\ApprovalResource;
use App\Models\Approval;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\DelegationService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalRequestController extends ApiController
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflow,
        private readonly DelegationService $delegations
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $approvals = Approval::query()
            ->with('approvalRule')
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->orderBy('level_no')
            ->get()
            ->filter(function (Approval $approval) use ($user): bool {
                $rule = $approval->approvalRule;

                if (! $rule instanceof ApprovalRule) {
                    return false;
                }

                $level = $rule->levelConfig($approval->level_no);

                if ($level === null) {
                    return false;
                }

                $expectedUserId = $level['approver_user_id'] ?? null;

                if ($expectedUserId !== null) {
                    if ((int) $expectedUserId === (int) $user->id) {
                        return true;
                    }

                    $delegate = $this->delegations->resolveActiveDelegate(
                        $approval->company_id,
                        (int) $expectedUserId,
                        Carbon::today()
                    );

                    return $delegate !== null && (int) $delegate->delegate_user_id === (int) $user->id;
                }

                $role = $level['approver_role'] ?? null;

                if ($role !== null) {
                    return $user->role === $role;
                }

                return false;
            })
            ->values();

        return $this->ok(
            ApprovalResource::collection($approvals)->resolve(),
            'Pending approvals fetched.'
        );
    }

    public function show(Request $request, Approval $approval): JsonResponse
    {
        if (! $this->canAccessApproval($request, $approval)) {
            return $this->fail('Approval not accessible.', 403);
        }

        return $this->ok((new ApprovalResource($approval))->toArray($request));
    }

    public function action(ApproveRequestActionRequest $request, Approval $approval): JsonResponse
    {
        if (! $this->canAccessApproval($request, $approval)) {
            return $this->fail('Approval not accessible.', 403);
        }

        $user = $this->resolveRequestUser($request);
        $decision = $request->input('decision');
        $comment = $request->input('comment');

        $this->workflow->processApproval($approval, $decision, $comment, $user);

        $next = Approval::query()
            ->where('company_id', $approval->company_id)
            ->where('target_type', $approval->target_type)
            ->where('target_id', $approval->target_id)
            ->where('status', 'pending')
            ->orderBy('level_no')
            ->first();

        $nextApproverId = null;
        $nextApproverRole = null;
        if ($next instanceof Approval) {
            $rule = $next->approvalRule;
            $level = $rule?->levelConfig($next->level_no);
            $nextApproverId = $level['approver_user_id'] ?? null;
            $nextApproverRole = $level['approver_role'] ?? null;
        }

        return $this->ok([
            'approval' => (new ApprovalResource($approval->fresh()))->toArray($request),
            'next_approver_user_id' => $nextApproverId !== null ? (int) $nextApproverId : null,
            'next_approver_role' => $nextApproverRole,
        ], 'Approval processed.');
    }

    private function canAccessApproval(Request $request, Approval $approval): bool
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User || ! $user->company instanceof Company) {
            return false;
        }

        if ((int) $approval->company_id !== (int) $user->company->id) {
            return false;
        }

        $rule = $approval->approvalRule;
        $level = $rule?->levelConfig($approval->level_no);

        if ($level === null) {
            return false;
        }

        $expectedUserId = $level['approver_user_id'] ?? null;

        if ($expectedUserId !== null && (int) $expectedUserId === (int) $user->id) {
            return true;
        }

        if ($expectedUserId !== null) {
            $delegate = $this->delegations->resolveActiveDelegate(
                $approval->company_id,
                (int) $expectedUserId,
                Carbon::today()
            );

            if ($delegate !== null && (int) $delegate->delegate_user_id === (int) $user->id) {
                return true;
            }
        }

        $role = $level['approver_role'] ?? null;

        return $role !== null && $user->role === $role;
    }
}
