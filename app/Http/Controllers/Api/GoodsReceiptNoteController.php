<?php

namespace App\Http\Controllers\Api;

use App\Actions\Receiving\AttachGoodsReceiptFileAction;
use App\Actions\Receiving\CreateGoodsReceiptNoteAction;
use App\Actions\Receiving\DeleteGoodsReceiptNoteAction;
use App\Actions\Receiving\UpdateGoodsReceiptNoteAction;
use App\Http\Requests\Receiving\AttachGoodsReceiptFileRequest;
use App\Http\Requests\Receiving\CompanyStoreGoodsReceiptRequest;
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
        private readonly AttachGoodsReceiptFileAction $attachFileAction,
    ) {}

    public function companyIndex(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'viewAny', GoodsReceiptNote::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $companyId = $user->company_id;

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $perPage = (int) $request->integer('per_page', 25);
        $perPage = max(1, min(100, $perPage));
        $cursor = $request->query('cursor');

        $query = GoodsReceiptNote::query()
            ->where('company_id', $companyId)
            ->with([
                'purchaseOrder' => fn ($purchaseOrder) => $purchaseOrder->select(['id', 'company_id', 'supplier_id', 'po_number']),
                'purchaseOrder.supplier:id,name',
                'inspector:id,name',
            ])
            ->withCount(['lines'])
            ->withCount('attachments');

        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', (int) $request->query('purchase_order_id'));
        }

        if ($request->filled('supplier_id')) {
            $supplierId = (int) $request->query('supplier_id');
            $query->whereHas('purchaseOrder', fn ($purchaseOrder) => $purchaseOrder->where('supplier_id', $supplierId));
        }

        if ($request->filled('received_from')) {
            $query->whereDate('inspected_at', '>=', $request->query('received_from'));
        }

        if ($request->filled('received_to')) {
            $query->whereDate('inspected_at', '<=', $request->query('received_to'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', fn ($purchaseOrder) => $purchaseOrder->where('po_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = $this->normalizeStatusFilter((string) $request->query('status'));

            if ($status === null) {
                return $this->fail('Invalid status filter.', 422);
            }

            $query->whereIn('status', $status);
        }

        $query->orderByDesc('inspected_at')->orderByDesc('id');

        $paginator = $query->cursorPaginate($perPage, ['*'], 'cursor', $cursor);

        $items = collect($paginator->items())
            ->map(fn ($note) => (new GoodsReceiptNoteResource($note))->toArray($request))
            ->all();

        return $this->ok([
            'items' => $items,
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => optional($paginator->nextCursor())->encode(),
                'prev_cursor' => optional($paginator->previousCursor())->encode(),
            ],
        ]);
    }

    public function companyShow(Request $request, string $note): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $record = $this->findCompanyNote(
            $user->company_id,
            $note,
            ['lines.purchaseOrderLine', 'inspector', 'purchaseOrder.supplier', 'attachments']
        );

        if ($record === null) {
            return $this->fail('Not found.', 404);
        }

        if ($this->authorizeDenied($user, 'view', $record)) {
            return $this->fail('Forbidden.', 403);
        }

        $attachmentMap = $this->loadAttachments($record);

        return $this->ok((new GoodsReceiptNoteResource($record, $attachmentMap))->toArray($request));
    }

    public function companyStore(CompanyStoreGoodsReceiptRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'create', GoodsReceiptNote::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $purchaseOrderId = (int) $request->validated('purchase_order_id');

        $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrderId)->first();

        if ($purchaseOrder === null || ! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

        $result = $this->createAction->execute($user, $purchaseOrder, $request->payload($user));

        $note = $result['note']->load(['lines.purchaseOrderLine', 'inspector', 'purchaseOrder.supplier', 'attachments']);

        $attachmentMap = $this->loadAttachments($note);

        return $this->ok(
            (new GoodsReceiptNoteResource($note, $attachmentMap))->toArray($request),
            'Goods receipt note recorded.',
        );
    }

    public function companyAttachFile(AttachGoodsReceiptFileRequest $request, string $note): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $record = $this->findCompanyNote(
            $user->company_id,
            $note,
            ['lines.purchaseOrderLine', 'inspector', 'purchaseOrder.supplier', 'attachments']
        );

        if ($record === null) {
            return $this->fail('Not found.', 404);
        }

        if ($this->authorizeDenied($user, 'update', $record)) {
            return $this->fail('Forbidden.', 403);
        }

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        $this->attachFileAction->execute($user, $record, $file);

        $record->load(['attachments']);

        $attachmentMap = $this->loadAttachments($record);

        return $this->ok(
            (new GoodsReceiptNoteResource($record, $attachmentMap))->toArray($request),
            'Attachment uploaded.',
        );
    }

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

    /**
     * @return list<string>|null
     */
    private function normalizeStatusFilter(string $status): ?array
    {
        return match (strtolower($status)) {
            'draft' => ['pending', 'draft'],
            'posted' => ['complete', 'accepted'],
            'variance', 'ncr_raised' => ['ncr_raised'],
            default => null,
        };
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
