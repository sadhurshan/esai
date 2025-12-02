<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\AssignPlanToCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyStatusRequest;
use App\Http\Resources\Admin\CompanyResource;
use App\Models\Company;
use App\Models\Plan;
use App\Services\Admin\CompanyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CompanyController extends ApiController
{
    public function __construct(private readonly CompanyService $companyService)
    {
    }

    public function assignPlan(AssignPlanToCompanyRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();

        $plan = Plan::findOrFail($validated['plan_id']);

        $company = $this->companyService->assignPlan($company, $plan, $validated);

        return $this->ok([
            'company' => (new CompanyResource($company->loadMissing('plan')))->toArray($request),
        ], 'Plan assignment updated.')->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function updateStatus(UpdateCompanyStatusRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();

        $company = $this->companyService->updateStatus($company, $request->statusEnum(), $validated);

        return $this->ok([
            'company' => (new CompanyResource($company->loadMissing('plan')))->toArray($request),
        ], 'Company status updated.');
    }
}
