<?php

namespace App\Http\Controllers\Api;

use App\Actions\Invoicing\CreateInvoiceAction;
use App\Actions\Invoicing\DeleteInvoiceAction;
use App\Actions\Invoicing\PerformInvoiceMatchAction;
use App\Actions\Invoicing\UpdateInvoiceAction;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
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

        if ($this->authorizeDenied($user, 'viewAny', Invoice::class)) {
            return $this->fail('Forbidden.', 403);
        }

        $query = $purchaseOrder->invoices()->with(['document']);

        if ($status = $request->query('status')) {
            $allowed = ['pending', 'paid', 'overdue', 'disputed'];
            if (! in_array($status, $allowed, true)) {
                return $this->fail('Invalid status filter.', 422);
            }

            $query->where('status', $status);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($this->perPage($request))->withQueryString();

        $result = $this->paginate($paginator, $request, InvoiceResource::class);

        return $this->ok($result);
    }

    public function store(StoreInvoiceRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
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

    $invoice->load(['lines.taxes.taxCode', 'document', 'matches']);

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

    $invoice->load(['lines.taxes.taxCode', 'document', 'matches', 'purchaseOrder']);

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

    $invoice->load(['lines.taxes.taxCode', 'document', 'matches']);

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

    private function purchaseOrderAccessible(PurchaseOrder $purchaseOrder, ?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return (int) $purchaseOrder->company_id === (int) $companyId;
    }
}
