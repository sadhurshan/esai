<?php

namespace App\Http\Controllers\Api\Billing;

use App\Actions\Billing\StartPlanCheckoutAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Billing\StartPlanCheckoutRequest;
use App\Models\Company;
use App\Models\Plan;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PlanCheckoutController extends ApiController
{
    public function __construct(
        private readonly StartPlanCheckoutAction $startPlanCheckout,
        private readonly StripeCheckoutService $checkoutService
    ) {
    }

    public function store(StartPlanCheckoutRequest $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['companyId' => $companyId] = $context;

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'code' => 'company_context_missing',
            ]);
        }

        $planCode = $request->string('plan_code')->lower();
        $plan = Plan::query()->where('code', $planCode)->first();

        if ($plan === null) {
            return $this->error('Plan not found.', Response::HTTP_NOT_FOUND);
        }

        if (! $this->checkoutService->requiresCheckout($plan)) {
            return $this->ok([
                'requires_checkout' => false,
                'plan' => $plan->code,
            ], 'This plan does not require checkout. Use plan selection instead.');
        }

        $checkout = $this->startPlanCheckout->execute($company, $plan);

        return $this->ok([
            'requires_checkout' => true,
            'checkout' => [
                'provider' => $checkout['provider'] ?? 'stripe',
                'session_id' => $checkout['session_id'] ?? null,
                'checkout_url' => $checkout['checkout_url'] ?? null,
                'status' => $checkout['status'] ?? null,
            ],
        ], 'Checkout session created.');
    }
}
