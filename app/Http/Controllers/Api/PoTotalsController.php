<?php

namespace App\Http\Controllers\Api;

use App\Actions\PurchaseOrder\RecalculatePurchaseOrderTotalsAction;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoTotalsController extends ApiController
{
    public function __construct(private readonly RecalculatePurchaseOrderTotalsAction $recalculatePurchaseOrderTotals)
    {
    }

    public function recalculate(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Active company context required.', 422, [
                'code' => 'company_context_missing',
            ]);
        }

        if ($user->company_id === null) {
            $user->company_id = $companyId;
        }

        if ($this->authorizeDenied($user, 'update', $purchaseOrder)) {
            return $this->fail('Forbidden.', 403);
        }

        if ((int) $purchaseOrder->company_id !== $companyId) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

        $purchaseOrder = $this->recalculatePurchaseOrderTotals->execute($purchaseOrder);

        $purchaseOrder->loadMissing(['lines.taxes.taxCode', 'supplier', 'rfq', 'changeOrders']);

        return $this->ok((new PurchaseOrderResource($purchaseOrder))->toArray($request), 'Purchase order totals recalculated.');
    }
}
