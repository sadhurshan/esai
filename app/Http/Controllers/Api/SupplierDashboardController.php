<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\ComputeSupplierDashboardMetricsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierDashboardController extends ApiController
{
    public function __construct(private readonly ComputeSupplierDashboardMetricsAction $computeSupplierDashboardMetrics)
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

        $supplierId = $request->attributes->get('acting_supplier_id');

        if (! is_numeric($supplierId)) {
            return $this->fail('Supplier persona required.', 403, [
                'code' => 'supplier_persona_required',
            ]);
        }

        $metrics = $this->computeSupplierDashboardMetrics->execute($companyId, (int) $supplierId);

        return $this->ok($metrics, 'Supplier dashboard metrics retrieved.');
    }
}
