<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\ComputeDashboardMetricsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private readonly ComputeDashboardMetricsAction $computeDashboardMetrics)
    {
    }

    public function metrics(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $metrics = $this->computeDashboardMetrics->execute($companyId);

        return $this->ok($metrics, 'Dashboard metrics retrieved.');
    }
}
