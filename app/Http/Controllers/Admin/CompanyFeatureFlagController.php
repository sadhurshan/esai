<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreCompanyFeatureFlagRequest;
use App\Http\Requests\Admin\UpdateCompanyFeatureFlagRequest;
use App\Http\Resources\Admin\CompanyFeatureFlagResource;
use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Services\Admin\CompanyFeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyFeatureFlagController extends ApiController
{
    public function __construct(private readonly CompanyFeatureFlagService $service)
    {
    }

    public function index(Request $request, Company $company): JsonResponse
    {
        $this->authorize('viewAny', CompanyFeatureFlag::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $paginator = $company->featureFlags()
            ->orderBy('key')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, CompanyFeatureFlagResource::class);

        return $this->ok([
            'items' => $paginated['items'],
        ], 'Feature flags retrieved.', $paginated['meta']);
    }

    public function store(StoreCompanyFeatureFlagRequest $request, Company $company): JsonResponse
    {
        $flag = $this->service->create($company, $request->validated());

        $response = $this->ok([
            'feature_flag' => (new CompanyFeatureFlagResource($flag))->toArray($request),
        ], 'Feature flag created.');

        return $response->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCompanyFeatureFlagRequest $request, Company $company, CompanyFeatureFlag $flag): JsonResponse
    {
        $this->ensureFlagForCompany($company, $flag);

        $flag = $this->service->update($flag, $request->validated());

        return $this->ok([
            'feature_flag' => (new CompanyFeatureFlagResource($flag))->toArray($request),
        ], 'Feature flag updated.');
    }

    public function destroy(Company $company, CompanyFeatureFlag $flag): JsonResponse
    {
        $this->authorize('delete', $flag);

        $this->ensureFlagForCompany($company, $flag);

        $this->service->delete($flag);

        return $this->ok(null, 'Feature flag deleted.');
    }

    private function ensureFlagForCompany(Company $company, CompanyFeatureFlag $flag): void
    {
        if ($flag->company_id !== $company->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

}
