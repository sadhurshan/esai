<?php

namespace App\Http\Controllers\Api;

use App\Actions\Company\AssignCompanyPlanAction;
use App\Actions\Company\EnsureComplimentarySubscriptionAction;
use App\Actions\Company\EnsureStubSubscriptionAction;
use App\Http\Requests\PlanSelectionRequest;
use App\Http\Resources\PlanCatalogResource;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CompanyPlanController extends ApiController
{
    public function __construct(
        private readonly AssignCompanyPlanAction $assignCompanyPlanAction,
        private readonly EnsureComplimentarySubscriptionAction $ensureComplimentarySubscriptionAction,
        private readonly EnsureStubSubscriptionAction $ensureStubSubscriptionAction,
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

        $trialAttributes = [];

        if ($this->shouldStartStubTrial($plan)) {
            $trialAttributes['trial_ends_at'] = Carbon::now()->addDays((int) config('services.stripe.stub_trial_days', 90));
        }

        $updatedCompany = DB::transaction(function () use ($company, $plan, $trialAttributes) {
            $assigned = $this->assignCompanyPlanAction->execute($company, $plan, $trialAttributes);

            if ($this->ensureComplimentarySubscriptionAction->supports($plan)) {
                $this->ensureComplimentarySubscriptionAction->execute($assigned, $plan);
            } else {
                $this->ensureStubSubscriptionAction->execute($assigned, $plan, $assigned->trial_ends_at);
            }

            return $assigned;
        });

        return $this->ok([
            'company' => $this->companyPayload($updatedCompany),
            'plan' => PlanCatalogResource::make($plan),
        ], 'Plan selection saved.');
    }

    private function requiresPlanSelection(Company $company): bool
    {
        return ! $company->plan_id && ! $company->plan_code;
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company): array
    {
        $company->loadMissing('plan');

        return [
            'id' => $company->id,
            'plan' => $company->plan_code ?? $company->plan?->code,
            'billing_status' => $company->billingStatus(),
            'requires_plan_selection' => $this->requiresPlanSelection($company),
        ];
    }

    private function shouldStartStubTrial(Plan $plan): bool
    {
        if ($plan->price_usd === null) {
            return false;
        }

        return (float) $plan->price_usd > 0;
    }
}
