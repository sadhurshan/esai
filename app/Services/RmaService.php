<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\RmaStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Rma;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CreditNoteService;
use App\Services\SupplierRiskService;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use App\Support\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class RmaService
{
    /**
     * @param  list<string>  $reviewerRoles
     */
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStorer $documentStorer,
        private readonly NotificationService $notifications,
        private readonly CreditNoteService $creditNotes,
        private readonly SupplierRiskService $supplierRisk,
        private readonly array $reviewerRoles = ['buyer_admin', 'quality', 'finance'],
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<UploadedFile>  $attachments
     */
    public function createRma(
        Company $company,
        PurchaseOrder $purchaseOrder,
        ?PurchaseOrderLine $line,
        ?GoodsReceiptNote $grn,
        User $submitter,
        array $attributes,
        array $attachments = []
    ): Rma {
        $company->loadMissing('plan');

        $plan = $company->plan;
        if ($plan !== null) {
            if (! $plan->rma_enabled) {
                throw ValidationException::withMessages([
                    'plan' => ['Upgrade required to access RMAs.'],
                ]);
            }

            if ((int) $plan->rma_monthly_limit > 0 && (int) $company->rma_monthly_used >= (int) $plan->rma_monthly_limit) {
                throw ValidationException::withMessages([
                    'plan' => ['Monthly RMA limit reached.'],
                ]);
            }
        }

        $this->assertCompanyOwnership($company, $purchaseOrder, $line, $grn);
        $this->assertPurchaseOrderEligible($purchaseOrder, $grn);

        /** @var array<string, mixed> $payload */
        $payload = Arr::only($attributes, [
            'reason',
            'description',
            'resolution_requested',
            'defect_qty',
        ]);

        if (! isset($payload['reason']) || trim((string) $payload['reason']) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Reason is required.'],
            ]);
        }

        if (! in_array($attributes['resolution_requested'] ?? null, ['repair', 'replacement', 'credit', 'refund', 'other'], true)) {
            throw ValidationException::withMessages([
                'resolution_requested' => ['Invalid resolution requested.'],
            ]);
        }

        return DB::transaction(function () use ($company, $purchaseOrder, $line, $grn, $submitter, $payload, $attachments): Rma {
            $rma = Rma::create([
                'company_id' => $company->id,
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_line_id' => $line?->id,
                'grn_id' => $grn?->id,
                'submitted_by' => $submitter->id,
                'reason' => $payload['reason'],
                'description' => $payload['description'] ?? null,
                'resolution_requested' => $payload['resolution_requested'],
                'defect_qty' => $this->resolveDefectQuantity($payload, $line),
                'status' => RmaStatus::Raised,
            ]);

            $this->auditLogger->created($rma, $rma->toArray());

            foreach ($attachments as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $document = $this->documentStorer->store(
                    $submitter,
                    $file,
                    DocumentCategory::Qa->value,
                    $company->id,
                    Rma::class,
                    $rma->id,
                    [
                        'kind' => DocumentKind::Other->value,
                        'visibility' => 'company',
                    ]
                );

                $rma->documents()->attach($document->id);
            }

            $company->increment('rma_monthly_used');

            $this->notifyTeams(
                $company,
                'rma.raised',
                'Return request submitted',
                sprintf('A new RMA has been raised for PO %s.', $purchaseOrder->po_number),
                $rma,
                [
                    'resolution_requested' => $rma->resolution_requested,
                ]
            );

            $this->updateSupplierMetrics($rma, $purchaseOrder);

            return $rma->fresh(['documents']);
        });
    }

    public function reviewRma(Rma $rma, string $decision, ?string $comment, User $reviewer): Rma
    {
        $decision = strtolower($decision);

        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Decision must be approve or reject.'],
            ]);
        }

        if (! in_array($reviewer->role, $this->reviewerRoles, true)) {
            throw ValidationException::withMessages([
                'decision' => ['You are not authorised to review RMAs.'],
            ]);
        }

        if (! $rma->isReviewable()) {
            throw ValidationException::withMessages([
                'rma' => ['RMA is not in a reviewable state.'],
            ]);
        }

        return DB::transaction(function () use ($rma, $decision, $comment, $reviewer): Rma {
            if ($rma->status === RmaStatus::Raised) {
                $before = $rma->getOriginal();
                $rma->status = RmaStatus::UnderReview;
                $rma->save();
                $this->auditLogger->updated($rma, $before, $rma->toArray());
            }

            $status = $decision === 'approve' ? RmaStatus::Approved : RmaStatus::Rejected;

            $before = $rma->getOriginal();
            $rma->status = $status;
            $rma->review_outcome = $status === RmaStatus::Approved ? 'approved' : 'rejected';
            $rma->review_comment = $comment;
            $rma->reviewed_by = $reviewer->id;
            $rma->reviewed_at = Carbon::now();
            $rma->save();

            $this->auditLogger->updated($rma, $before, $rma->toArray());

            $this->notifyTeams(
                $rma->company,
                'rma.reviewed',
                'RMA review completed',
                sprintf('RMA #%d has been %s.', $rma->id, $status->value),
                $rma,
                [
                    'decision' => $status->value,
                ]
            );

            if ($status === RmaStatus::Approved && in_array($rma->resolution_requested, ['credit', 'refund'], true)) {
                $this->triggerCreditNote($rma, $reviewer);
            }

            $purchaseOrder = $rma->purchaseOrder()->first();

            $this->closeRma($rma);

            if ($purchaseOrder instanceof PurchaseOrder) {
                $this->updateSupplierMetrics($rma, $purchaseOrder);
            }

            return $rma->fresh(['documents']);
        });
    }

    private function assertCompanyOwnership(
        Company $company,
        PurchaseOrder $purchaseOrder,
        ?PurchaseOrderLine $line,
        ?GoodsReceiptNote $grn
    ): void {
        if ((int) $purchaseOrder->company_id !== (int) $company->id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order not accessible.'],
            ]);
        }

        if ($line !== null && (int) $line->purchase_order_id !== (int) $purchaseOrder->id) {
            throw ValidationException::withMessages([
                'purchase_order_line_id' => ['Line does not belong to the selected purchase order.'],
            ]);
        }

        if ($grn !== null && (int) $grn->purchase_order_id !== (int) $purchaseOrder->id) {
            throw ValidationException::withMessages([
                'grn_id' => ['Goods receipt note does not match the selected purchase order.'],
            ]);
        }
    }

    private function assertPurchaseOrderEligible(PurchaseOrder $purchaseOrder, ?GoodsReceiptNote $grn): void
    {
        $eligibleStatuses = ['confirmed', 'acknowledged'];
        $hasCompletedGrn = $grn?->status === 'complete'
            || $purchaseOrder->goodsReceiptNotes()->where('status', 'complete')->exists();

        if (! in_array($purchaseOrder->status, $eligibleStatuses, true) && ! $hasCompletedGrn) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['RMA can only be created for delivered orders.'],
            ]);
        }
    }

    private function closeRma(Rma $rma): void
    {
        $before = $rma->getOriginal();
        $rma->status = RmaStatus::Closed;
        $rma->save();

        $this->auditLogger->updated($rma, $before, $rma->toArray());

        $this->notifyTeams(
            $rma->company,
            'rma.closed',
            'RMA closed',
            sprintf('RMA #%d has been closed.', $rma->id),
            $rma,
            [
                'decision' => $rma->review_outcome,
            ]
        );
    }

    private function notifyTeams(
        ?Company $company,
        string $event,
        string $title,
        string $body,
        Rma $rma,
        array $meta = []
    ): void {
        if (! $company instanceof Company) {
            return;
        }

        $recipients = $company->users()
            ->whereIn('role', ['buyer_admin', 'quality', 'finance'])
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $recipients,
            $event,
            $title,
            $body,
            Rma::class,
            $rma->id,
            $meta
        );
    }

    private function resolveDefectQuantity(array $payload, ?PurchaseOrderLine $line): int
    {
        $inputQuantity = isset($payload['defect_qty']) ? (int) $payload['defect_qty'] : null;

        if ($inputQuantity !== null && $inputQuantity > 0) {
            return $inputQuantity;
        }

        if ($line !== null && (int) $line->quantity > 0) {
            return (int) $line->quantity;
        }

        return 1;
    }

    private function triggerCreditNote(Rma $rma, User $reviewer): ?CreditNote
    {
        if ($rma->credit_note_id !== null) {
            return null;
        }

        $rma->loadMissing(['company.plan', 'purchaseOrder.supplier', 'purchaseOrderLine']);

        $company = $rma->company;
        $plan = $company?->plan;

        if ($plan !== null && ! $plan->credit_notes_enabled) {
            return null;
        }

        $purchaseOrder = $rma->purchaseOrder;

        if (! $purchaseOrder instanceof PurchaseOrder) {
            return null;
        }

        $invoice = $this->resolveInvoiceForRma($rma, $purchaseOrder);

        if (! $invoice instanceof Invoice) {
            return null;
        }

        $amount = $this->resolveCreditAmount($rma, $invoice);

        if ($amount === null) {
            return null;
        }

        $payload = [
            'reason' => sprintf('RMA #%d resolved (%s)', $rma->id, $rma->resolution_requested),
            'amount' => $amount,
        ];

        if ($rma->grn_id !== null) {
            $payload['grn_id'] = $rma->grn_id;
        }

        $creditNote = $this->creditNotes->createCreditNote(
            $invoice,
            $purchaseOrder,
            $payload,
            $reviewer
        );

        $rma->credit_note_id = $creditNote->id;
        $rma->save();

        return $creditNote;
    }

    private function resolveInvoiceForRma(Rma $rma, PurchaseOrder $purchaseOrder): ?Invoice
    {
        $lineId = $rma->purchase_order_line_id;

        if ($lineId !== null) {
            $invoiceWithLine = $purchaseOrder->invoices()
                ->whereHas('lines', function (Builder $builder) use ($lineId): void {
                    $builder->where('po_line_id', $lineId);
                })
                ->orderByDesc('created_at')
                ->first();

            if ($invoiceWithLine instanceof Invoice) {
                $invoiceWithLine->loadMissing('lines');

                return $invoiceWithLine;
            }
        }

        $invoice = $purchaseOrder->invoices()
            ->orderByDesc('created_at')
            ->first();

        if ($invoice instanceof Invoice) {
            $invoice->loadMissing('lines');
        }

        return $invoice;
    }

    private function resolveCreditAmount(Rma $rma, Invoice $invoice): ?string
    {
        $amount = 0.0;
        $invoice->loadMissing('lines');
        $lineId = $rma->purchase_order_line_id;

        if ($lineId !== null && $invoice->relationLoaded('lines')) {
            $lineAmount = $invoice->lines
                ->where('po_line_id', $lineId)
                ->reduce(
                    fn (float $carry, InvoiceLine $line): float => $carry + ((float) $line->quantity * (float) $line->unit_price),
                    0.0
                );

            if ($lineAmount > 0) {
                $lineQuantity = $rma->purchaseOrderLine?->quantity;
                $defectQty = $rma->defect_qty;

                if ($defectQty !== null && $lineQuantity !== null && (int) $lineQuantity > 0) {
                    $ratio = min(1.0, $defectQty / (int) $lineQuantity);
                    $lineAmount *= $ratio;
                }

                $amount = $lineAmount;
            }
        }

        if ($amount <= 0) {
            $amount = (float) $invoice->total;
        }

        if ($amount <= 0) {
            return null;
        }

        return number_format($amount, 2, '.', '');
    }

    private function updateSupplierMetrics(Rma $rma, PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('supplier');

        $supplier = $purchaseOrder->supplier;

        if (! $supplier instanceof Supplier) {
            return;
        }

        $periodStart = Carbon::now()->copy()->startOfMonth();
        $periodEnd = Carbon::now();

        try {
            $this->supplierRisk->calculateForSupplier($supplier, $periodStart, $periodEnd);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
