<?php

namespace App\Services;

use App\Enums\CreditNoteStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use App\Support\Notifications\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreditNoteService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
        private readonly DocumentStorer $documentStorer,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $attachments
     */
    public function createCreditNote(
        Invoice $invoice,
        PurchaseOrder $purchaseOrder,
        array $payload,
        User $creator,
        array $attachments = []
    ): CreditNote {
        if ((int) $invoice->company_id !== (int) $purchaseOrder->company_id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Invoice and purchase order must belong to the same company.'],
            ]);
        }

        if ((int) $invoice->purchase_order_id !== (int) $purchaseOrder->id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Invoice must reference the selected purchase order.'],
            ]);
        }

        $companyId = (int) $invoice->company_id;
        $grnId = $payload['grn_id'] ?? null;

    $amount = $payload['amount'] ?? $invoice->total;
    // TODO: align default amount with unresolved RMA balances when credit-matching metrics ship.

        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $invoice,
            $purchaseOrder,
            $payload,
            $creator,
            $attachments,
            $companyId,
            $amount,
            $grnId
        ): CreditNote {
            $creditNote = CreditNote::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'purchase_order_id' => $purchaseOrder->id,
                'grn_id' => $grnId,
                'credit_number' => $this->generateCreditNumber($companyId),
                'currency' => $invoice->currency,
                'amount' => (float) $amount,
                'reason' => $payload['reason'],
                'status' => CreditNoteStatus::Draft,
                'review_comment' => null,
            ]);

            $this->auditLogger->created($creditNote, $creditNote->toArray());

            foreach ($attachments as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $document = $this->documentStorer->store(
                    $creator,
                    $file,
                    DocumentCategory::Financial->value,
                    $companyId,
                    CreditNote::class,
                    $creditNote->id,
                    [
                        'kind' => DocumentKind::Other->value,
                        'visibility' => 'company',
                    ]
                );

                $creditNote->documents()->attach($document->id);
            }

            return $creditNote->fresh(['documents']);
        });
    }

    public function issueCreditNote(CreditNote $creditNote, User $issuer): CreditNote
    {
        if ($creditNote->status !== CreditNoteStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Only draft credit notes can be issued.'],
            ]);
        }

        return DB::transaction(function () use ($creditNote, $issuer): CreditNote {
            $before = $creditNote->getOriginal();

            $creditNote->status = CreditNoteStatus::Issued;
            $creditNote->issued_by = $issuer->id;
            $creditNote->save();

            $this->auditLogger->updated($creditNote, $before, $creditNote->toArray());

            $this->notifyCompanyRoles(
                $creditNote,
                ['finance', 'buyer_admin'],
                'credit_note.issued',
                'Credit note issued',
                sprintf('Credit note %s has been issued and is awaiting approval.', $creditNote->credit_number)
            );

            return $creditNote->fresh(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents']);
        });
    }

    public function approveCreditNote(CreditNote $creditNote, User $approver, string $decision, ?string $comment = null): CreditNote
    {
        $decision = strtolower($decision);

        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Decision must be approve or reject.'],
            ]);
        }

        if ($creditNote->status !== CreditNoteStatus::Issued) {
            throw ValidationException::withMessages([
                'status' => ['Only issued credit notes can be reviewed.'],
            ]);
        }

        return DB::transaction(function () use ($creditNote, $approver, $decision, $comment): CreditNote {
            $before = $creditNote->getOriginal();

            $creditNote->approved_by = $approver->id;
            $creditNote->approved_at = now();
            $creditNote->review_comment = $comment;

            if ($decision === 'reject') {
                $creditNote->status = CreditNoteStatus::Rejected;
                $creditNote->save();

                $this->auditLogger->updated($creditNote, $before, $creditNote->toArray());

                $this->notifyCompanyRoles(
                    $creditNote,
                    ['buyer_admin', 'finance'],
                    'credit_note.rejected',
                    'Credit note rejected',
                    sprintf('Credit note %s has been rejected.', $creditNote->credit_number),
                );

                return $creditNote->fresh(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents']);
            }

            $creditNote->status = CreditNoteStatus::Approved;
            $creditNote->save();

            $this->auditLogger->updated($creditNote, $before, $creditNote->toArray());

            $invoice = $creditNote->invoice()->lockForUpdate()->first();

            if (! $invoice instanceof Invoice) {
                throw ValidationException::withMessages([
                    'invoice' => ['Unable to locate invoice for credit application.'],
                ]);
            }

            $invoiceBefore = ['total' => $invoice->total];
            $invoice->total = max($invoice->total - $creditNote->amount, 0);
            $invoice->save();

            $this->auditLogger->updated($invoice, $invoiceBefore, ['total' => $invoice->total]);

            $creditNoteBefore = $creditNote->getOriginal();
            $creditNote->status = CreditNoteStatus::Applied;
            $creditNote->save();

            $this->auditLogger->updated($creditNote, $creditNoteBefore, $creditNote->toArray());

            $creditNote->company?->increment('credit_notes_monthly_used');

            $this->notifyCompanyRoles(
                $creditNote,
                ['buyer_admin', 'finance'],
                'credit_note.approved',
                'Credit note applied',
                sprintf('Credit note %s has been approved and applied to invoice %s.', $creditNote->credit_number, $invoice->invoice_number),
            );

            return $creditNote->fresh(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents']);
        });
    }

    private function generateCreditNumber(int $companyId): string
    {
        $prefix = sprintf('CN-%s', now()->format('Ymd'));
        $counter = CreditNote::withTrashed()
            ->where('company_id', $companyId)
            ->where('credit_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $candidate = sprintf('%s-%s', $prefix, Str::padLeft((string) $counter, 4, '0'));
            $exists = CreditNote::withTrashed()
                ->where('company_id', $companyId)
                ->where('credit_number', $candidate)
                ->exists();
            $counter++;
        } while ($exists);

        return $candidate;
    }

    private function notifyCompanyRoles(
        CreditNote $creditNote,
        array $roles,
        string $creditEvent,
        string $title,
        string $body
    ): void {
        $company = $creditNote->company;

        if ($company === null) {
            return;
        }

        $recipients = $company->users()
            ->whereIn('role', array_values(array_unique($roles)))
            ->get()
            ->filter(fn (User $user) => $user->id !== $creditNote->issued_by);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifications->send(
            $recipients,
            'invoice_status_changed',
            $title,
            $body,
            CreditNote::class,
            $creditNote->id,
            [
                'credit_number' => $creditNote->credit_number,
                'status' => $creditNote->status instanceof CreditNoteStatus ? $creditNote->status->value : $creditNote->status,
                'credit_event' => $creditEvent,
            ]
        );
    }
}
