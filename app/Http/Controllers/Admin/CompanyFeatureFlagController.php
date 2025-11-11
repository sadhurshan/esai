<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyFeatureFlagRequest;
use App\Http\Requests\Admin\UpdateCompanyFeatureFlagRequest;
use App\Http\Resources\Admin\CompanyFeatureFlagResource;
use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Services\Admin\CompanyFeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class CompanyFeatureFlagController extends Controller
{
    public function __construct(private readonly CompanyFeatureFlagService $service)
    {
    }

    public function index(Request $request, Company $company): JsonResponse
    {
        $this->authorize('viewAny', CompanyFeatureFlag::class);

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $flags = $company->featureFlags()
            ->orderBy('key')
            ->cursorPaginate($perPage);

        return $this->paginatedResponse($flags, 'Feature flags retrieved.');
    }

    public function store(StoreCompanyFeatureFlagRequest $request, Company $company): JsonResponse
    {
        $flag = $this->service->create($company, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Feature flag created.',
            'data' => [
                'feature_flag' => CompanyFeatureFlagResource::make($flag),
            ],
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateCompanyFeatureFlagRequest $request, Company $company, CompanyFeatureFlag $flag): JsonResponse
    {
        $this->ensureFlagForCompany($company, $flag);

        $flag = $this->service->update($flag, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Feature flag updated.',
            'data' => [
                'feature_flag' => CompanyFeatureFlagResource::make($flag),
            ],
        ]);
    }

    public function destroy(Company $company, CompanyFeatureFlag $flag): JsonResponse
    {
        $this->authorize('delete', $flag);

        $this->ensureFlagForCompany($company, $flag);

        $this->service->delete($flag);

        return response()->json([
            'status' => 'success',
            'message' => 'Feature flag deleted.',
            'data' => null,
        ]);
    }

    private function ensureFlagForCompany(Company $company, CompanyFeatureFlag $flag): void
    {
        if ($flag->company_id !== $company->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    private function paginatedResponse(CursorPaginator $paginator, string $message): JsonResponse
    {
        $items = CompanyFeatureFlagResource::collection(collect($paginator->items()))->resolve(request());

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'items' => $items,
                'meta' => [
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'prev_cursor' => $paginator->previousCursor()?->encode(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}
