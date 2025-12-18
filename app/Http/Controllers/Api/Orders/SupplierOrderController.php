<?php

namespace App\Http\Controllers\Api\Orders;

use App\Actions\PurchaseOrder\HandleSupplierAcknowledgementAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Orders\AcknowledgeOrderRequest;
use App\Http\Requests\Orders\ListSupplierOrdersRequest;
use App\Http\Resources\SalesOrderDetailResource;
use App\Http\Resources\SalesOrderSummaryResource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use App\Support\CompanyContext;

class SupplierOrderController extends ApiController
{
    public function __construct(
        private readonly HandleSupplierAcknowledgementAction $handleSupplierAcknowledgement,
    ) {}

    public function index(ListSupplierOrdersRequest $request): JsonResponse
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

        return CompanyContext::bypass(function () use ($request, $supplierCompanyId, $buyerCompanyId) {
            $query = $this->baseQuery()
                ->where('supplier_company_id', $supplierCompanyId)
                ->where('company_id', $buyerCompanyId);
            $this->applyFilters($query, $request);

            $paginator = $query
                ->cursorPaginate(
                    $this->resolvePerPage($request->integer('per_page')),
                    ['*'],
                    'cursor',
                    $request->input('cursor')
                )
                ->withQueryString();

            $items = SalesOrderSummaryResource::collection($paginator->items())->toArray($request);

            $meta = [
                'next_cursor' => optional($paginator->nextCursor())->encode(),
                'prev_cursor' => optional($paginator->previousCursor())->encode(),
                'per_page' => $paginator->perPage(),
            ];

            return $this->ok([
                'items' => $items,
            ], null, $meta);
        });
    }

    public function show(string $orderId, ListSupplierOrdersRequest $request): JsonResponse
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

        return CompanyContext::bypass(function () use ($supplierCompanyId, $buyerCompanyId, $orderId, $request) {
            $order = $this->detailQuery()
                ->where('supplier_company_id', $supplierCompanyId)
                ->where('company_id', $buyerCompanyId)
                ->whereKey($orderId)
                ->first();

            if ($order === null) {
                return $this->fail('Sales order not found.', 404);
            }

            return $this->ok((new SalesOrderDetailResource($order))->toArray($request));
        });
    }

    public function acknowledge(string $orderId, AcknowledgeOrderRequest $request): JsonResponse
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

        return CompanyContext::bypass(function () use ($user, $supplierCompanyId, $buyerCompanyId, $orderId, $request) {
            $order = $this->detailQuery()
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

            $reason = $request->validated()['reason'] ?? null;

            $this->handleSupplierAcknowledgement->execute(
                $user,
                $purchaseOrder,
                $request->decision(),
                $reason,
            );

            $projection = $this->detailQuery()
                ->where('supplier_company_id', $supplierCompanyId)
                ->where('company_id', $buyerCompanyId)
                ->whereKey($order->getKey())
                ->first();

            if ($projection === null) {
                return $this->fail('Sales order projection unavailable.', 404);
            }

            return $this->ok((new SalesOrderDetailResource($projection))->toArray($request));
        });
    }

    private function baseQuery(): Builder
    {
        return Order::query()
            ->with(['company', 'supplierCompany'])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');
    }

    private function detailQuery(): Builder
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

    private function applyFilters(Builder $query, ListSupplierOrdersRequest $request): void
    {
        $this->applyStatusFilters($query, $request->statuses());

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('ordered_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('ordered_at', '<=', $dateTo);
        }

        if ($search = $request->input('search')) {
            $like = '%' . $search . '%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->where('so_number', 'like', $like)
                    ->orWhereHas('company', fn (Builder $company) => $company->where('name', 'like', $like));
            });
        }
    }

    private function applyStatusFilters(Builder $query, array $statuses): void
    {
        if (empty($statuses)) {
            return;
        }

        $query->where(function (Builder $builder) use ($statuses): void {
            foreach ($statuses as $status) {
                $builder->orWhere(function (Builder $clause) use ($status): void {
                    match ($status) {
                        'draft' => $clause
                            ->where('status', 'pending')
                            ->where('metadata->po_status', 'draft'),
                        'pending_ack' => $clause
                            ->where('status', 'pending')
                            ->where('metadata->po_status', 'sent'),
                        'accepted' => $clause->where('status', 'in_production'),
                        'partially_fulfilled' => $clause->where('status', 'in_transit'),
                        'fulfilled' => $clause->where('status', 'delivered'),
                        'cancelled' => $clause->where('status', 'cancelled'),
                        default => $clause->whereRaw('0 = 1'),
                    };
                });
            }
        });
    }

    private function resolvePerPage(?int $candidate): int
    {
        $perPage = $candidate ?? 25;

        if ($perPage < 1) {
            $perPage = 25;
        }

        return min($perPage, 100);
    }
}
