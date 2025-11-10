<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Approval\StoreApprovalRuleRequest;
use App\Http\Resources\ApprovalRuleResource;
use App\Models\ApprovalRule;
use App\Models\Company;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalRuleController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
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

        $query = ApprovalRule::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at');

        $targetType = $request->query('target_type');

        if ($targetType !== null) {
            $allowed = ['rfq', 'purchase_order', 'change_order', 'invoice', 'ncr'];

            if (! in_array($targetType, $allowed, true)) {
                return $this->fail('Invalid target type.', 422, [
                    'target_type' => ['Target type must be one of: '.implode(', ', $allowed).'.'],
                ]);
            }

            $query->where('target_type', $targetType);
        }

        $rules = $query->get();

        return $this->ok(
            ApprovalRuleResource::collection($rules)->resolve(),
            'Approval rules retrieved.'
        );
    }

    public function store(StoreApprovalRuleRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $data = $request->validated();
        $data['company_id'] = $user->company->id;

        $rule = ApprovalRule::create($data);

        $this->auditLogger->created($rule, $rule->toArray());

        return $this->ok(
            (new ApprovalRuleResource($rule))->toArray($request),
            'Approval rule saved.'
        )->setStatusCode(201);
    }

    public function show(Request $request, ApprovalRule $rule): JsonResponse
    {
        if (! $this->ensureOwnership($request, $rule)) {
            return $this->fail('Approval rule not accessible.', 403);
        }

        return $this->ok(
            (new ApprovalRuleResource($rule))->toArray($request)
        );
    }

    public function update(StoreApprovalRuleRequest $request, ApprovalRule $rule): JsonResponse
    {
        if (! $this->ensureOwnership($request, $rule)) {
            return $this->fail('Approval rule not accessible.', 403);
        }

        $data = $request->validated();
        $before = $rule->getOriginal();

        $rule->fill($data);
        $rule->save();

        $this->auditLogger->updated($rule, $before, $rule->toArray());

        return $this->ok(
            (new ApprovalRuleResource($rule))->toArray($request),
            'Approval rule updated.'
        );
    }

    public function destroy(Request $request, ApprovalRule $rule): JsonResponse
    {
        if (! $this->ensureOwnership($request, $rule)) {
            return $this->fail('Approval rule not accessible.', 403);
        }

        $before = $rule->getOriginal();
        $rule->active = false;
        $rule->save();
        $rule->delete();

        $this->auditLogger->deleted($rule, $before);

        return $this->ok(null, 'Approval rule deactivated.');
    }

    private function ensureOwnership(Request $request, ApprovalRule $rule): bool
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User || ! $user->company instanceof Company) {
            return false;
        }

        return (int) $rule->company_id === (int) $user->company->id;
    }
}
