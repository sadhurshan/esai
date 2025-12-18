<?php

namespace App\Http\Controllers\Api\Orders;

use App\Actions\PurchaseOrder\CreatePurchaseOrderShipmentAction;
use App\Actions\PurchaseOrder\UpdatePurchaseOrderShipmentStatusAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Orders\CreateShipmentRequest;
use App\Http\Requests\Orders\UpdateShipmentStatusRequest;
use App\Http\Resources\SalesOrderDetailResource;
use App\Models\Order;
use App\Models\PurchaseOrderShipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use App\Support\CompanyContext;

class SupplierShipmentController extends ApiController
{
    public function __construct(
        private readonly CreatePurchaseOrderShipmentAction $createShipment,
        private readonly UpdatePurchaseOrderShipmentStatusAction $updateShipmentStatus,
    ) {}

    public function store(string $orderId, CreateShipmentRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $workspace = $this->resolveSupplierWorkspaceContext($user);
        $supplierCompanyId = $workspace['supplierCompanyId'];
        $buyerCompanyId = $workspace['buyerCompanyId'];

        if ($supplierCompanyId === null || $buyerCompanyId === null) {
            return $this->fail('Supplier persona required.', 403, [
                'code' => 'supplier_persona_required',
            ]);
        }

        return CompanyContext::bypass(function () use ($supplierCompanyId, $buyerCompanyId, $orderId, $request, $user) {
            $order = $this->orderDetailQuery()
                ->where('supplier_company_id', $supplierCompanyId)
                ->where('company_id', $buyerCompanyId)
                ->whereKey($orderId)
                ->first();

            if ($order === null) {
                return $this->fail('Sales order not found.', 404);
            }

            $purchaseOrder = $order->relationLoaded('purchaseOrder') ? $order->getRelation('purchaseOrder') : null;

            if ($purchaseOrder === null) {
                return $this->fail('Purchase order context missing.', 409);
            }

            $this->createShipment->execute(
                $purchaseOrder,
                $user,
                $request->validated(),
                $request->lines(),
            );

            $projection = $this->orderDetailQuery()
                ->where('supplier_company_id', $supplierCompanyId)
                ->where('company_id', $buyerCompanyId)
                ->whereKey($order->getKey())
                ->firstOrFail();

            return $this->ok((new SalesOrderDetailResource($projection))->toArray($request));
        });
    }

    public function updateStatus(string $shipmentId, UpdateShipmentStatusRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $workspace = $this->resolveSupplierWorkspaceContext($user);
        $supplierCompanyId = $workspace['supplierCompanyId'];
        $buyerCompanyId = $workspace['buyerCompanyId'];

        if ($supplierCompanyId === null || $buyerCompanyId === null) {
            return $this->fail('Supplier persona required.', 403, [
                'code' => 'supplier_persona_required',
            ]);
        }

        return CompanyContext::bypass(function () use ($supplierCompanyId, $buyerCompanyId, $shipmentId, $request, $user) {
            $shipment = PurchaseOrderShipment::query()
                ->where('supplier_company_id', $supplierCompanyId)
                ->where('company_id', $buyerCompanyId)
                ->whereKey($shipmentId)
                ->first();

            if ($shipment === null) {
                return $this->fail('Shipment not found.', 404);
            }

            $updatedShipment = $this->updateShipmentStatus->execute(
                $shipment,
                $user,
                $request->status(),
                $request->deliveredAt(),
            );

            $projection = $this->orderDetailQuery()
                ->where('supplier_company_id', $supplierCompanyId)
                ->where('company_id', $buyerCompanyId)
                ->where('purchase_order_id', $updatedShipment->purchase_order_id)
                ->firstOrFail();

            return $this->ok((new SalesOrderDetailResource($projection))->toArray($request));
        });
    }

    private function orderDetailQuery(): Builder
    {
        return Order::query()
            ->with([
                'company',
                'supplierCompany',
                'purchaseOrder.company',
                'purchaseOrder.supplier',
                'purchaseOrder.quote.supplier',
                'purchaseOrder.lines.shipmentLines.shipment',
                'purchaseOrder.shipments.lines',
                'purchaseOrder.events' => fn ($query) => $query->orderByDesc('occurred_at'),
            ]);
    }
}
