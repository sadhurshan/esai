<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PurchaseOrderShipmentResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderShipmentController extends ApiController
{
    public function index(Request $request, string $purchaseOrderId): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context missing.', 422);
        }

        $order = PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->whereKey($purchaseOrderId)
            ->first();

        if ($order === null) {
            return $this->fail('Purchase order not found.', 404);
        }

        $shipments = $order->shipments()
            ->with([
                'lines.purchaseOrderLine',
            ])
            ->orderByDesc('shipped_at')
            ->orderByDesc('id')
            ->get();

        $items = PurchaseOrderShipmentResource::collection($shipments)->toArray($request);

        return $this->ok([
            'purchaseOrderId' => $order->getKey(),
            'items' => $items,
        ]);
    }
}
