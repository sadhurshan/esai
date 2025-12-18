<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Billing\BillingPortalSessionRequest;
use App\Models\Company;
use App\Services\Billing\StripeBillingPortalService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class BillingPortalController extends ApiController
{
    public function __construct(private readonly StripeBillingPortalService $billingPortal)
    {
    }

    public function store(BillingPortalSessionRequest $request): JsonResponse
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

        $result = $this->billingPortal->createPortalSession($company);

        if (! $result->successful) {
            return $this->fail($result->message ?? 'Unable to open the billing portal.', Response::HTTP_BAD_GATEWAY, [
                'code' => $result->code ?? 'billing_portal_unavailable',
                'fallback_url' => $result->fallbackUrl,
            ]);
        }

        return $this->ok([
            'portal' => [
                'provider' => 'stripe',
                'url' => $result->portalUrl,
            ],
        ], 'Billing portal session created.');
    }
}
