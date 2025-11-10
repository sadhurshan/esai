<?php

namespace App\Http\Controllers\Api;

use App\Enums\CreditNoteStatus;
use App\Http\Requests\CreditNote\ReviewCreditNoteRequest;
use App\Http\Requests\CreditNote\StoreCreditNoteRequest;
use App\Http\Resources\CreditNoteResource;
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
    public function __construct(private readonly CreditNoteService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        $query = CreditNote::query()
            ->where('company_id', $company->id)
            ->with(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents'])
            ->latest('created_at');

        if ($status = $request->query('status')) {
            if (! in_array($status, CreditNoteStatus::values(), true)) {
                return $this->fail('Invalid status filter supplied.', 422, [
                    'status' => ['Status must be one of: '.implode(', ', CreditNoteStatus::values()).'.'],
                ]);
            }

            $query->where('status', $status);
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

        if ($to = $request->query('created_to')) {
            try {
                $toDate = Carbon::parse($to)->endOfDay();
                $query->whereDate('created_at', '<=', $toDate);
            } catch (\Throwable) {
                // Ignore invalid dates; client-side validation expected.
            }
        }

        $perPage = $this->perPage($request, 15, 50);
        $paginator = $query->paginate($perPage);

        $result = $this->paginate($paginator, $request, CreditNoteResource::class);

        return $this->ok($result, 'Credit notes retrieved.');
    }

    public function store(StoreCreditNoteRequest $request, Invoice $invoice): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $invoice->company_id !== (int) $user->company_id) {
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

        if ((int) $creditNote->company_id !== (int) $user->company_id) {
            return $this->fail('Credit note not accessible.', 403);
        }

        $creditNote->load(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents']);

        return $this->ok((new CreditNoteResource($creditNote))->toArray($request));
    }

    public function issue(Request $request, CreditNote $creditNote): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if (! $user instanceof User) {
            return $this->fail('Authentication required.', 401);
        }

        if ((int) $creditNote->company_id !== (int) $user->company_id) {
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

        if ((int) $creditNote->company_id !== (int) $user->company_id) {
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
}
