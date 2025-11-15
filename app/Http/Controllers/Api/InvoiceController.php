<?php

namespace App\Http\Controllers\Api;

use App\Actions\Invoicing\AttachInvoiceFileAction;
use App\Actions\Invoicing\CreateInvoiceAction;
use App\Actions\Invoicing\DeleteInvoiceAction;
use App\Actions\Invoicing\PerformInvoiceMatchAction;
use App\Actions\Invoicing\UpdateInvoiceAction;
use App\Http\Requests\Invoice\AttachInvoiceFileRequest;
use App\Http\Requests\Invoice\CreateInvoiceFromPurchaseOrderRequest;
use App\Http\Requests\Invoice\ListInvoicesRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\DocumentResource;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends ApiController
{
    public function __construct(
        private readonly CreateInvoiceAction $createInvoiceAction,
        private readonly UpdateInvoiceAction $updateInvoiceAction,
        private readonly DeleteInvoiceAction $deleteInvoiceAction,
        private readonly PerformInvoiceMatchAction $performInvoiceMatchAction,
        private readonly AttachInvoiceFileAction $attachInvoiceFileAction,
    ) {}

    public function index(ListInvoicesRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

        if ($this->authorizeDenied($user, 'viewAny', Invoice::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $query = $purchaseOrder->invoices()->with(['document', 'attachments', 'supplier', 'purchaseOrder']);

        $filters = $request->payload();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('invoice_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('invoice_date', '<=', $filters['to']);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($this->perPage($request))->withQueryString();

        $result = $this->paginate($paginator, $request, InvoiceResource::class);

        return $this->ok($result);
    }

    public function list(ListInvoicesRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 422);
        }

        if ($this->authorizeDenied($user, 'viewAny', Invoice::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $filters = $request->payload();

        $query = Invoice::query()
            ->where('company_id', $companyId)
            ->with(['supplier', 'purchaseOrder', 'document', 'attachments']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('invoice_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('invoice_date', '<=', $filters['to']);
        }

        $paginator = $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        $result = $this->paginate($paginator, $request, InvoiceResource::class);

        return $this->ok($result);
    }

    public function store(CreateInvoiceFromPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

        if ($this->authorizeDenied($user, 'create', Invoice::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $invoice = $this->createInvoiceAction->execute($user, $purchaseOrder, $request->payload());

        $this->performInvoiceMatchAction->execute($invoice);

        $invoice->load(['lines.taxes.taxCode', 'document', 'attachments', 'matches', 'supplier', 'purchaseOrder']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request), 'Invoice created.');
    }

    public function storeFromPo(CreateInvoiceFromPurchaseOrderRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $payload = $request->payload();
        $purchaseOrderId = $payload['po_id'] ?? null;

        if (! is_int($purchaseOrderId) || $purchaseOrderId <= 0) {
            return $this->fail('Purchase order is required.', 422, ['po_id' => ['A valid purchase order is required.']]);
        }

        $purchaseOrder = PurchaseOrder::query()->whereKey($purchaseOrderId)->first();

        if ($purchaseOrder === null) {
            return $this->fail('Purchase order not found.', 404);
        }

        if (! $this->purchaseOrderAccessible($purchaseOrder, $user->company_id)) {
            return $this->fail('Purchase order not found for this company.', 404);
        }

        if ($this->authorizeDenied($user, 'create', Invoice::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $invoice = $this->createInvoiceAction->execute($user, $purchaseOrder, $payload);

        $this->performInvoiceMatchAction->execute($invoice);

        $invoice->load(['lines.taxes.taxCode', 'document', 'attachments', 'matches', 'supplier', 'purchaseOrder']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request), 'Invoice created.');
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'view', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        $invoice->load(['lines.taxes.taxCode', 'document', 'attachments', 'matches', 'purchaseOrder', 'supplier']);

        if ($user->company_id === null || (int) $invoice->company_id !== (int) $user->company_id) {
            return $this->fail('Invoice not found for this company.', 404);
        }

        return $this->ok((new InvoiceResource($invoice))->toArray($request));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'update', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        $invoice = $this->updateInvoiceAction->execute($user, $invoice, $request->payload());

        $this->performInvoiceMatchAction->execute($invoice);

        $invoice->load(['lines.taxes.taxCode', 'document', 'attachments', 'matches', 'supplier', 'purchaseOrder']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request), 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'delete', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        $this->deleteInvoiceAction->execute($user, $invoice);

        return $this->ok(null, 'Invoice deleted.');
    }

    public function attachFile(AttachInvoiceFileRequest $request, Invoice $invoice): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        if ($this->authorizeDenied($user, 'update', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = $request->payload();

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $payload['file'];

        $document = $this->attachInvoiceFileAction->execute($user, $invoice, $file);

        $invoice->load(['lines.taxes.taxCode', 'document', 'attachments', 'matches', 'supplier', 'purchaseOrder']);

        return $this->ok([
            'invoice' => (new InvoiceResource($invoice))->toArray($request),
            'attachment' => (new DocumentResource($document))->toArray($request),
        ], 'Invoice attachment uploaded.');
    }

    private function purchaseOrderAccessible(PurchaseOrder $purchaseOrder, ?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return (int) $purchaseOrder->company_id === (int) $companyId;
    }
}
