<?php

namespace App\Http\Controllers\Api;

use App\Actions\Company\AssignCompanyPlanAction;
use App\Actions\Company\EnsureComplimentarySubscriptionAction;
use App\Http\Requests\PlanSelectionRequest;
use App\Http\Resources\PlanCatalogResource;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CompanyPlanController extends ApiController
{
    public function __construct(
        private readonly AssignCompanyPlanAction $assignCompanyPlanAction,
        private readonly EnsureComplimentarySubscriptionAction $ensureComplimentarySubscriptionAction,
    ) {
    }

    public function store(PlanSelectionRequest $request): JsonResponse
    {
        $user = $request->user();
        $company = $user?->company;

        if ($company === null) {
            return $this->fail('No company linked to this user.', 422);
        }

        $plan = Plan::query()->firstWhere('code', $request->validated('plan_code'));

        if ($plan === null) {
            return $this->fail('The selected plan is unavailable.', 422);
        }

        $updatedCompany = DB::transaction(function () use ($company, $plan) {
            // TODO: Wire paid plan checkout before activating non-zero plans.
            $assigned = $this->assignCompanyPlanAction->execute($company, $plan);

            $this->ensureComplimentarySubscriptionAction->execute($assigned, $plan);

            return $assigned;
        });

        return $this->ok([
            'company' => [
                'id' => $updatedCompany->id,
                'plan' => $updatedCompany->plan_code ?? $updatedCompany->plan?->code,
                'billing_status' => $updatedCompany->billingStatus(),
                'requires_plan_selection' => $this->requiresPlanSelection($updatedCompany),
            ],
            'plan' => PlanCatalogResource::make($plan),
        ], 'Plan selection saved.');
    }

    private function requiresPlanSelection(Company $company): bool
    {
        $status = $company->billingStatus();

        if (! $company->plan_id && ! $company->plan_code) {
            return true;
        }

        return ! in_array($status, ['active', 'trialing'], true);
    }
}
