<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->company_id === null, 403);

        $isSupplierListing = $request->boolean('supplier') === true;

        $query = PurchaseOrder::query()->with(['quote.supplier', 'rfq']);

        if ($isSupplierListing) {
            $query->whereHas('quote.supplier', function ($supplierQuery) use ($user): void {
                $supplierQuery->where('company_id', $user->company_id);
            });
        } else {
            $query->where('company_id', $user->company_id);
        }

        $statusParam = $request->query('status');
        $statusFilter = $this->normalizeStatusFilter($statusParam);

        if (is_array($statusFilter) && $statusFilter !== []) {
            $query->whereIn('status', $statusFilter);
        } elseif (is_string($statusFilter) && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, PurchaseOrderResource::class);

        return $this->ok([
            'items' => $items,
            'meta' => $meta,
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        $isBuyer = $user->company_id === $purchaseOrder->company_id;
        $isSupplier = $supplierCompanyId !== null && $supplierCompanyId === $user->company_id;

        abort_if(! $isBuyer && ! $isSupplier, 403);

        $purchaseOrder->load(['lines', 'rfq', 'quote.supplier', 'changeOrders.proposedByUser']);

        return $this->ok((new PurchaseOrderResource($purchaseOrder))->toArray($request));
    }

    public function send(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->company_id !== $purchaseOrder->company_id, 403);

        if ($purchaseOrder->status !== 'draft') {
            return $this->fail('Only draft purchase orders can be sent.', 422);
        }

        $purchaseOrder->status = 'sent';
        $purchaseOrder->save();

        Log::info('purchase_order.sent', [
            'purchase_order_id' => $purchaseOrder->id,
            'sent_by_user_id' => $user->id,
        ]);

        $purchaseOrder->load(['lines', 'rfq', 'quote.supplier']);

        return $this->ok((new PurchaseOrderResource($purchaseOrder))->toArray($request), 'Purchase order issued.');
    }

    public function acknowledge(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        abort_if($supplierCompanyId === null || $supplierCompanyId !== $user->company_id, 403);

        if ($purchaseOrder->status !== 'sent') {
            return $this->fail('Only sent purchase orders can be acknowledged.', 422);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:accept,reject'],
        ]);

        $action = $validated['action'];

        if ($action === 'accept') {
            $purchaseOrder->status = 'acknowledged';
        }

        if ($action === 'reject') {
            $purchaseOrder->status = 'cancelled';
        }

        $purchaseOrder->save();

        Log::info('purchase_order.acknowledged', [
            'purchase_order_id' => $purchaseOrder->id,
            'action' => $action,
            'actor_user_id' => $user->id,
        ]);

        $purchaseOrder->load(['lines', 'rfq', 'quote.supplier']);

        $message = $action === 'accept' ? 'Purchase order acknowledged.' : 'Purchase order rejected by supplier.';

        return $this->ok((new PurchaseOrderResource($purchaseOrder))->toArray($request), $message);
    }

    private function normalizeStatusFilter(mixed $statusParam): array|string|null
    {
        if (is_array($statusParam)) {
            return array_values(array_filter(array_map('strval', $statusParam), fn (string $value): bool => $value !== ''));
        }

        if (is_string($statusParam)) {
            $parts = array_filter(array_map('trim', explode(',', $statusParam)));

            if (count($parts) > 1) {
                return array_values($parts);
            }

            return $parts[0] ?? null;
        }

        return null;
    }
}
