<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPlanToCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyStatusRequest;
use App\Http\Resources\Admin\CompanyResource;
use App\Models\Company;
use App\Models\Plan;
use App\Services\Admin\CompanyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companyService)
    {
    }

    public function assignPlan(AssignPlanToCompanyRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();

        $plan = Plan::findOrFail($validated['plan_id']);

        $company = $this->companyService->assignPlan($company, $plan, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Plan assignment updated.',
            'data' => [
                'company' => CompanyResource::make($company->loadMissing('plan')),
            ],
        ], Response::HTTP_ACCEPTED);
    }

    public function updateStatus(UpdateCompanyStatusRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();

    $company = $this->companyService->updateStatus($company, $request->statusEnum(), $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Company status updated.',
            'data' => [
                'company' => CompanyResource::make($company->loadMissing('plan')),
            ],
        ]);
    }
}
