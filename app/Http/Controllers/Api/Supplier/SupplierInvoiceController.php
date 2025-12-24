<?php

namespace App\Http\Controllers\Api\Supplier;

use App\Actions\Invoicing\CreateInvoiceAction;
use App\Actions\Invoicing\PerformInvoiceMatchAction;
use App\Actions\Invoicing\SubmitSupplierInvoiceAction;
use App\Actions\Invoicing\UpdateInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Supplier\Invoices\SupplierInvoiceIndexRequest;
use App\Http\Requests\Supplier\Invoices\SupplierInvoiceStoreRequest;
use App\Http\Requests\Supplier\Invoices\SupplierInvoiceSubmitRequest;
use App\Http\Requests\Supplier\Invoices\SupplierInvoiceUpdateRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Support\CompanyContext;
use App\Support\PurchaseOrders\PurchaseOrderSupplierResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SupplierInvoiceController extends ApiController
{
    public function __construct(
        private readonly CreateInvoiceAction $createInvoiceAction,
        private readonly UpdateInvoiceAction $updateInvoiceAction,
        private readonly SubmitSupplierInvoiceAction $submitInvoiceAction,
        private readonly PerformInvoiceMatchAction $performInvoiceMatchAction,
    ) {}

    public function index(SupplierInvoiceIndexRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $workspace = $this->resolveSupplierWorkspaceContext($user);
        $supplierCompanyId = $workspace['supplierCompanyId'];

        if ($supplierCompanyId === null) {
            return $this->fail('Supplier company context missing.', 422);
        }

        if (CompanyContext::get() === null) {
            return $this->fail('Active buyer context required.', 422);
        }

        $filters = $request->payload();

        $query = Invoice::query()
            ->with(['purchaseOrder', 'attachments', 'document', 'supplierCompany', 'payments.creator'])
            ->where('supplier_company_id', $supplierCompanyId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['po_number'])) {
            $poNumber = $filters['po_number'];
            $query->whereHas('purchaseOrder', function (Builder $builder) use ($poNumber): void {
                $builder->where('po_number', 'like', $poNumber.'%');
            });
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('invoice_number', 'like', $search)
                    ->orWhere('payment_reference', 'like', $search);
            });
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                $this->perPage($request, 25, 50),
                ['*'],
                'cursor',
                $request->query('cursor')
            )
            ->withQueryString();

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, InvoiceResource::class);

        return $this->ok(['items' => $items], null, $meta);
    }

    public function show(Invoice $invoice, SupplierInvoiceIndexRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $workspace = $this->resolveSupplierWorkspaceContext($user);
        $supplierCompanyId = $workspace['supplierCompanyId'];

        if ($supplierCompanyId === null || ! $this->invoiceBelongsToSupplier($invoice, $supplierCompanyId)) {
            return $this->fail('Invoice not found.', 404);
        }

        $invoice->load(['lines.taxes.taxCode', 'purchaseOrder', 'attachments', 'document', 'matches', 'supplierCompany', 'reviewedBy', 'payments.creator']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request));
    }

    public function store(PurchaseOrder $purchaseOrder, SupplierInvoiceStoreRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $workspace = $this->resolveSupplierWorkspaceContext($user);
        $supplierCompanyId = $workspace['supplierCompanyId'];

        if ($supplierCompanyId === null) {
            return $this->fail('Supplier company context missing.', 422);
        }

        if ($purchaseOrder->company_id !== CompanyContext::get()) {
            return $this->fail('Purchase order not found.', 404);
        }

        if (! $this->purchaseOrderBelongsToSupplier($purchaseOrder, $supplierCompanyId)) {
            return $this->fail('Purchase order not found for supplier.', 404);
        }

        if ($this->authorizeDenied($user, 'createSupplierInvoice', $purchaseOrder)) {
            return $this->fail('Forbidden.', 403);
        }

        $invoice = $this->createInvoiceAction->execute(
            $user,
            $purchaseOrder,
            $request->payload(),
            [
                'company_id' => $purchaseOrder->company_id,
                'supplier_company_id' => $supplierCompanyId,
                'status' => InvoiceStatus::Draft->value,
                'created_by_type' => 'supplier',
                'created_by_id' => $user->id,
            ]
        );

        $this->performInvoiceMatchAction->execute($invoice);

        $invoice->load(['lines.taxes.taxCode', 'purchaseOrder', 'attachments', 'document', 'matches', 'supplierCompany', 'reviewedBy', 'payments.creator']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request), 'Invoice draft created.');
    }

    public function update(Invoice $invoice, SupplierInvoiceUpdateRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $workspace = $this->resolveSupplierWorkspaceContext($user);
        $supplierCompanyId = $workspace['supplierCompanyId'];

        if ($supplierCompanyId === null || ! $this->invoiceBelongsToSupplier($invoice, $supplierCompanyId)) {
            return $this->fail('Invoice not found.', 404);
        }

        if ($this->authorizeDenied($user, 'supplierView', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        if ($this->authorizeDenied($user, 'supplierUpdate', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        if (! in_array($invoice->status, [InvoiceStatus::Draft->value, InvoiceStatus::Rejected->value, InvoiceStatus::BuyerReview->value], true)) {
            return $this->fail('Invoice can no longer be edited.', 409);
        }

        $payload = $request->payload();

        CompanyContext::forCompany($invoice->company_id, function () use ($invoice, $payload): void {
            $fields = Arr::only($payload, ['invoice_number', 'invoice_date', 'due_date']);

            if ($fields !== []) {
                foreach ($fields as $key => $value) {
                    $invoice->{$key} = $value;
                }

                $invoice->save();
            }
        });

        $invoice->refresh();

        if (! empty($payload['lines'])) {
            $invoice = $this->updateInvoiceAction->execute(
                $user,
                $invoice,
                $payload,
                [
                    'company_id' => $invoice->company_id,
                    'editable_statuses' => [InvoiceStatus::Draft->value, InvoiceStatus::Rejected->value, InvoiceStatus::BuyerReview->value],
                    'allowed_status_transitions' => [],
                    'prevent_revert_status' => null,
                ]
            );

            $this->performInvoiceMatchAction->execute($invoice);
        }

        $invoice->load(['lines.taxes.taxCode', 'purchaseOrder', 'attachments', 'document', 'matches', 'supplierCompany', 'reviewedBy', 'payments.creator']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request), 'Invoice updated.');
    }

    public function submit(Invoice $invoice, SupplierInvoiceSubmitRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $workspace = $this->resolveSupplierWorkspaceContext($user);
        $supplierCompanyId = $workspace['supplierCompanyId'];

        if ($supplierCompanyId === null || ! $this->invoiceBelongsToSupplier($invoice, $supplierCompanyId)) {
            return $this->fail('Invoice not found.', 404);
        }

        if ($this->authorizeDenied($user, 'supplierSubmit', $invoice)) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = $request->payload();

        $invoice = $this->submitInvoiceAction->execute($user, $invoice, $payload['note'] ?? null);

        $this->performInvoiceMatchAction->execute($invoice);

        $invoice->load(['lines.taxes.taxCode', 'purchaseOrder', 'attachments', 'document', 'matches', 'supplierCompany', 'reviewedBy']);

        return $this->ok((new InvoiceResource($invoice))->toArray($request), 'Invoice submitted.');
    }

    private function purchaseOrderBelongsToSupplier(PurchaseOrder $purchaseOrder, int $supplierCompanyId): bool
    {
        $supplierId = $this->purchaseOrderSupplierCompanyId($purchaseOrder);

        return $supplierId !== null && $supplierId === $supplierCompanyId;
    }

    private function invoiceBelongsToSupplier(Invoice $invoice, int $supplierCompanyId): bool
    {
        if ($invoice->supplier_company_id !== null) {
            return (int) $invoice->supplier_company_id === $supplierCompanyId;
        }

        $invoice->loadMissing('purchaseOrder.supplier', 'purchaseOrder.quote.supplier');

        $purchaseOrder = $invoice->purchaseOrder;

        if ($purchaseOrder === null) {
            return false;
        }

        return $this->purchaseOrderBelongsToSupplier($purchaseOrder, $supplierCompanyId);
    }

    private function purchaseOrderSupplierCompanyId(PurchaseOrder $purchaseOrder): ?int
    {
        return PurchaseOrderSupplierResolver::resolveSupplierCompanyId($purchaseOrder);
    }
}
