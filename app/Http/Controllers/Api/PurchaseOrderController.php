<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->company_id === null, 403);

        $query = PurchaseOrder::query()
            ->where('company_id', $user->company_id)
            ->with(['quote.supplier', 'rfq']);

        $status = $request->query('status');
        if (is_string($status)) {
            $query->where('status', $status);
        }

        if (is_array($status)) {
            $query->whereIn('status', $status);
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
        abort_if($user === null || $user->company_id !== $purchaseOrder->company_id, 403);

        $purchaseOrder->load(['lines', 'rfq', 'quote.supplier']);

        return $this->ok((new PurchaseOrderResource($purchaseOrder))->toArray($request));
    }
}
