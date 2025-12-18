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

        $paginator = $order->shipments()
            ->with([
                'lines.purchaseOrderLine',
            ])
            ->orderByDesc('shipped_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                $this->perPage($request, 25, 100),
                ['*'],
                'cursor',
                $request->query('cursor')
            )
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, PurchaseOrderShipmentResource::class);

        return $this->ok([
            'purchaseOrderId' => $order->getKey(),
            'items' => $items,
        ], null, $meta);
    }
}
