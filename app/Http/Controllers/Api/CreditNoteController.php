<?php

namespace App\Http\Controllers\Api;

use App\Actions\Invoicing\AttachCreditNoteFileAction;
use App\Enums\CreditNoteStatus;
use App\Http\Requests\CreditNote\AttachCreditNoteFileRequest;
use App\Http\Requests\CreditNote\ReviewCreditNoteRequest;
use App\Http\Requests\CreditNote\StoreCreditNoteRequest;
use App\Http\Requests\CreditNote\UpdateCreditNoteLinesRequest;
use App\Http\Resources\CreditNoteResource;
use App\Http\Resources\DocumentResource;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreditNoteController extends ApiController
{
    public function __construct(
        private readonly CreditNoteService $service,
        private readonly AttachCreditNoteFileAction $attachCreditNoteFile,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        $query = CreditNote::query()
            ->where('company_id', $companyId)
            ->with(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents'])
            ->latest('created_at')
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            if (! in_array($status, CreditNoteStatus::values(), true)) {
                return $this->fail('Invalid status filter supplied.', 422, [
                    'status' => ['Status must be one of: '.implode(', ', CreditNoteStatus::values()).'.'],
                ]);
            }

            $query->where('status', $status);
        }

        if ($supplierId = $request->query('supplier_id')) {
            $query->whereHas('invoice', function ($builder) use ($supplierId): void {
                $builder->where('supplier_id', (int) $supplierId);
            });
        }

        if ($invoiceId = $request->query('invoice_id')) {
            $query->where('invoice_id', (int) $invoiceId);
        }

        if ($from = $request->query('created_from')) {
            try {
                $fromDate = Carbon::parse($from)->startOfDay();
                $query->whereDate('created_at', '>=', $fromDate);
            } catch (\Throwable) {
                // Ignore invalid dates; client-side validation expected.
            }
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('credit_number', 'like', '%'.$search.'%')
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search): void {
                        $invoiceQuery->where('invoice_number', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($to = $request->query('created_to')) {
            try {
                $toDate = Carbon::parse($to)->endOfDay();
                $query->whereDate('created_at', '<=', $toDate);
            } catch (\Throwable) {
                // Ignore invalid dates; client-side validation expected.
            }
        }

        $perPage = $this->perPage($request, 15, 50);
        $paginator = $query->cursorPaginate($perPage);

        ['items' => $items, 'meta' => $meta] = $this->paginate($paginator, $request, CreditNoteResource::class);

        return $this->ok(['items' => $items], 'Credit notes retrieved.', $meta);
    }

    public function store(StoreCreditNoteRequest $request, Invoice $invoice): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $invoice->company_id !== (int) $companyId) {
            return $this->fail('Invoice not accessible.', 403);
        }

        $purchaseOrder = $invoice->purchaseOrder;

        if (! $purchaseOrder instanceof PurchaseOrder) {
            return $this->fail('Invoice missing purchase order context.', 422);
        }

        $validated = $request->validated();
        $attachments = collect($request->file('attachments', []))
            ->filter(fn ($file) => $file !== null)
            ->values()
            ->all();

        try {
            $creditNote = $this->service->createCreditNote(
                $invoice,
                $purchaseOrder,
                $validated,
                $user,
                $attachments
            );
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok(
            (new CreditNoteResource($creditNote))->toArray($request),
            'Credit note created.'
        )->setStatusCode(201);
    }

    public function show(Request $request, CreditNote $creditNote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $creditNote->company_id !== (int) $companyId) {
            return $this->fail('Credit note not accessible.', 403);
        }

        $detailed = $this->service->loadDetail($creditNote);

        return $this->ok((new CreditNoteResource($detailed))->toArray($request));
    }

    public function issue(Request $request, CreditNote $creditNote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $creditNote->company_id !== (int) $companyId) {
            return $this->fail('Credit note not accessible.', 403);
        }

        if (! in_array($user->role, ['buyer_admin', 'finance'], true)) {
            return $this->fail('Insufficient permissions to issue credit notes.', 403);
        }

        try {
            $updated = $this->service->issueCreditNote($creditNote, $user);
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok(
            (new CreditNoteResource($updated))->toArray($request),
            'Credit note issued.'
        );
    }

    public function approve(ReviewCreditNoteRequest $request, CreditNote $creditNote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $creditNote->company_id !== (int) $companyId) {
            return $this->fail('Credit note not accessible.', 403);
        }

        if (! in_array($user->role, ['buyer_admin', 'finance'], true)) {
            return $this->fail('Insufficient permissions to review credit notes.', 403);
        }

        try {
            $updated = $this->service->approveCreditNote(
                $creditNote,
                $user,
                $request->input('decision'),
                $request->input('comment')
            );
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        $message = $request->input('decision') === 'approve'
            ? 'Credit note approved.'
            : 'Credit note rejected.';

        return $this->ok(
            (new CreditNoteResource($updated))->toArray($request),
            $message
        );
    }

    public function updateLines(UpdateCreditNoteLinesRequest $request, CreditNote $creditNote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $creditNote->company_id !== (int) $companyId) {
            return $this->fail('Credit note not accessible.', 403);
        }

        $payload = $request->validated();
        $lines = $payload['lines'] ?? [];

        try {
            $updated = $this->service->updateCreditNoteLines($creditNote, $lines, $user);
        } catch (ValidationException $exception) {
            return $this->fail('Validation failed', 422, $exception->errors());
        }

        return $this->ok(
            (new CreditNoteResource($updated))->toArray($request),
            'Credit note lines updated.'
        );
    }

    public function attachFile(AttachCreditNoteFileRequest $request, CreditNote $creditNote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $companyId = $this->resolveUserCompanyId($user);

        if ($companyId === null) {
            return $this->fail('Company context required.', 403);
        }

        if ((int) $creditNote->company_id !== (int) $companyId) {
            return $this->fail('Credit note not accessible.', 403);
        }

        $payload = $request->payload();
        $file = $payload['file'];

        $document = $this->attachCreditNoteFile->execute($user, $creditNote, $file);

        $detailed = $this->service->loadDetail($creditNote);

        return $this->ok([
            'credit_note' => (new CreditNoteResource($detailed))->toArray($request),
            'attachment' => (new DocumentResource($document))->toArray($request),
        ], 'Credit note attachment uploaded.');
    }
}
