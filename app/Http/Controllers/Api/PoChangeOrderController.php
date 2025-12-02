<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PoChangeOrderResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PoChangeOrder;
use App\Models\PurchaseOrder;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PoChangeOrderController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        $isBuyer = $companyId === $purchaseOrder->company_id;
        $isSupplier = $supplierCompanyId !== null && $supplierCompanyId === $companyId;

        if (! $isBuyer && ! $isSupplier) {
            return $this->fail('Forbidden.', 403);
        }

        $changeOrders = $purchaseOrder->changeOrders()
            ->with(['proposedByUser'])
            ->orderByDesc('created_at')
            ->get();

        return $this->ok([
            'items' => PoChangeOrderResource::collection($changeOrders)->toArray($request),
        ]);
    }

    public function store(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $purchaseOrder->loadMissing(['quote.supplier']);

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;
        if ($supplierCompanyId === null || $companyId !== $supplierCompanyId) {
            return $this->fail('Forbidden.', 403);
        }

        $validated = $this->validateChangeOrderPayload($request);

        $changeOrder = PoChangeOrder::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'proposed_by_user_id' => $user->id,
            'changes_json' => $validated['changes_json'],
            'reason' => $validated['reason'],
            'status' => 'proposed',
        ]);

        $changeOrder->load('proposedByUser');

        $this->auditLogger->created($changeOrder, [
            'reason' => $changeOrder->reason,
            'changes_json' => $changeOrder->changes_json,
            'status' => $changeOrder->status,
        ]);

        return $this->ok((new PoChangeOrderResource($changeOrder))->toArray($request), 'Change order proposed successfully.')->setStatusCode(201);
    }

    public function approve(PoChangeOrder $changeOrder, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $changeOrder->loadMissing(['purchaseOrder.lines', 'purchaseOrder.quote.supplier']);

        $purchaseOrder = $changeOrder->purchaseOrder;
        if ($purchaseOrder === null) {
            return $this->fail('Change order not associated with a purchase order.', 404);
        }

        if ($companyId !== $purchaseOrder->company_id) {
            return $this->fail('Forbidden.', 403);
        }

        if ($changeOrder->status !== 'proposed') {
            return $this->fail('Only proposed change orders can be approved.', 422);
        }

        DB::transaction(function () use ($changeOrder, $purchaseOrder): void {
            $changes = $changeOrder->changes_json ?? [];

            $this->applyPurchaseOrderChanges($purchaseOrder, $changes);

            $currentRevision = (int) ($purchaseOrder->revision_no ?? 0);
            $purchaseOrder->revision_no = $currentRevision + 1;
            $purchaseOrder->save();

            $changeOrder->status = 'accepted';
            $changeOrder->po_revision_no = $purchaseOrder->revision_no;
            $changeOrder->save();
        });

        Log::info('po_change_order.approved', [
            'change_order_id' => $changeOrder->id,
            'purchase_order_id' => $changeOrder->purchase_order_id,
            'approved_by_user_id' => $user->id,
        ]);

        $this->auditLogger->custom($changeOrder, 'po_change_order_approved', [
            'approved_by_user_id' => $user->id,
            'purchase_order_id' => $changeOrder->purchase_order_id,
            'po_revision_no' => $changeOrder->po_revision_no,
        ]);

        $purchaseOrder->refresh()->load(['lines', 'rfq', 'quote.supplier', 'changeOrders.proposedByUser']);

        return $this->ok(
            (new PurchaseOrderResource($purchaseOrder))->toArray($request),
            'Purchase order revision created.'
        );
    }

    public function reject(PoChangeOrder $changeOrder, Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);
        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);
        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $changeOrder->loadMissing(['purchaseOrder.quote.supplier', 'proposedByUser']);

        $purchaseOrder = $changeOrder->purchaseOrder;
        if ($purchaseOrder === null) {
            return $this->fail('Change order not associated with a purchase order.', 404);
        }

        if ($companyId !== $purchaseOrder->company_id) {
            return $this->fail('Forbidden.', 403);
        }

        if ($changeOrder->status !== 'proposed') {
            return $this->fail('Only proposed change orders can be rejected.', 422);
        }

        $changeOrder->status = 'rejected';
        $changeOrder->save();

        Log::info('po_change_order.rejected', [
            'change_order_id' => $changeOrder->id,
            'purchase_order_id' => $changeOrder->purchase_order_id,
            'rejected_by_user_id' => $user->id,
        ]);

        $this->auditLogger->custom($changeOrder, 'po_change_order_rejected', [
            'rejected_by_user_id' => $user->id,
            'purchase_order_id' => $changeOrder->purchase_order_id,
        ]);

        return $this->ok((new PoChangeOrderResource($changeOrder))->toArray($request), 'Change order rejected.');
    }

    /**
     * @return array{reason: string, changes_json: array<array-key, mixed>}
     */
    private function validateChangeOrderPayload(Request $request): array
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'changes_json' => ['required', 'array', 'min:1'],
        ]);

        return [
            'reason' => $validated['reason'],
            'changes_json' => $validated['changes_json'],
        ];
    }

    private function applyPurchaseOrderChanges(PurchaseOrder $purchaseOrder, array $changes): void
    {
        if (isset($changes['purchase_order']) && is_array($changes['purchase_order'])) {
            $fillable = array_flip($purchaseOrder->getFillable());
            $updates = array_intersect_key($changes['purchase_order'], $fillable);

            if ($updates !== []) {
                $purchaseOrder->fill($updates);
            }
        }

        if (isset($changes['lines']) && is_array($changes['lines'])) {
            $allowedLineFields = ['description', 'quantity', 'uom', 'unit_price', 'delivery_date'];
            $lineMap = $purchaseOrder->lines->keyBy('id');

            foreach ($changes['lines'] as $lineChange) {
                if (! is_array($lineChange)) {
                    continue;
                }

                $lineId = Arr::get($lineChange, 'id');
                if (! $lineId || ! $lineMap->has($lineId)) {
                    continue;
                }

                $lineUpdates = array_intersect_key($lineChange, array_flip($allowedLineFields));
                if ($lineUpdates === []) {
                    continue;
                }

                $line = $lineMap->get($lineId);
                $line->fill($lineUpdates);
                $line->save();
            }
        }
    }

}
