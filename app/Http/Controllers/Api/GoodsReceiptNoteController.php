<?php

namespace App\Http\Controllers\Api;

use App\Actions\Receiving\CreateGoodsReceiptNoteAction;
use App\Actions\Receiving\DeleteGoodsReceiptNoteAction;
use App\Actions\Receiving\UpdateGoodsReceiptNoteAction;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Requests\UpdateGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptNoteResource;
use App\Models\Document;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptNoteController extends ApiController
{
    public function __construct(
        private readonly CreateGoodsReceiptNoteAction $createAction,
        private readonly UpdateGoodsReceiptNoteAction $updateAction,
        private readonly DeleteGoodsReceiptNoteAction $deleteAction,
    ) {}

    public function index(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

        if ($this->authorizeDenied($user, 'viewAny', GoodsReceiptNote::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $companyId = $user->company_id;

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $query = $purchaseOrder->goodsReceiptNotes()->with(['inspector']);

        $status = $request->query('status');
        if ($status !== null) {
            $allowedStatuses = ['pending', 'complete', 'ncr_raised'];
            if (! in_array($status, $allowedStatuses, true)) {
                return $this->fail('Invalid status filter.', 422);
            }

            $query->where('status', $status);
        }

        $query->orderByDesc('inspected_at')->orderByDesc('created_at');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        $items = collect($paginator->items())
            ->map(fn ($note) => (new GoodsReceiptNoteResource($note))->toArray($request))
            ->all();

        return $this->ok([
            'items' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'create', GoodsReceiptNote::class)) {
            return $this->fail('Forbidden.', 403);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

        $result = $this->createAction->execute($user, $purchaseOrder, $request->payload());

        $note = $result['note']->load(['lines', 'inspector']);

        $attachmentMap = $this->loadAttachments($note);

        return $this->ok(
            (new GoodsReceiptNoteResource($note, $attachmentMap))->toArray($request),
            'Goods receipt note recorded.'
        );
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder, string $note): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

    $note = $this->findCompanyNote($user->company_id, $note, ['lines', 'inspector']);

        if ($note === null) {
            return $this->fail('Not found.', 404);
        }

        if ((int) $note->purchase_order_id !== (int) $purchaseOrder->id) {
            return $this->fail('Not found.', 404);
        }

        if ($this->authorizeDenied($user, 'view', $note)) {
            return $this->fail('Forbidden.', 403);
        }

        $attachmentMap = $this->loadAttachments($note);

        return $this->ok((new GoodsReceiptNoteResource($note, $attachmentMap))->toArray($request));
    }

    public function update(UpdateGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder, string $note): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

    $note = $this->findCompanyNote($user->company_id, $note, ['lines', 'inspector']);

        if ($note === null) {
            return $this->fail('Not found.', 404);
        }

        if ((int) $note->purchase_order_id !== (int) $purchaseOrder->id) {
            return $this->fail('Not found.', 404);
        }

        if ($this->authorizeDenied($user, 'update', $note)) {
            return $this->fail('Forbidden.', 403);
        }

        $note = $this->updateAction->execute($user, $note, $request->payload())->load(['lines', 'inspector']);

        $attachmentMap = $this->loadAttachments($note);

        return $this->ok(
            (new GoodsReceiptNoteResource($note, $attachmentMap))->toArray($request),
            'Goods receipt note updated.'
        );
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, string $note): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

    $note = $this->findCompanyNote($user->company_id, $note, ['lines']);

        if ($note === null) {
            return $this->fail('Not found.', 404);
        }

        if ((int) $note->purchase_order_id !== (int) $purchaseOrder->id) {
            return $this->fail('Not found.', 404);
        }

        if ($this->authorizeDenied($user, 'delete', $note)) {
            return $this->fail('Forbidden.', 403);
        }

        $this->deleteAction->execute($note);

        return $this->ok(null, 'Goods receipt note deleted.');
    }

    private function purchaseOrderAccessible(PurchaseOrder $purchaseOrder, ?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return (int) $purchaseOrder->company_id === (int) $companyId;
    }

    private function findCompanyNote(?int $companyId, string $noteId, array $with = []): ?GoodsReceiptNote
    {
        if ($companyId === null) {
            return null;
        }

        return GoodsReceiptNote::query()
            ->with($with)
            ->where('company_id', $companyId)
            ->whereKey($noteId)
            ->first();
    }

    /**
     * @return array<int, Document>
     */
    private function loadAttachments(GoodsReceiptNote $note): array
    {
        if (! $note->relationLoaded('lines')) {
            return [];
        }

        $ids = $note->lines
            ->pluck('attachment_ids')
            ->flatten()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return Document::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }
}
