<?php

namespace App\Actions\Invoicing;

use App\Actions\PurchaseOrder\RecordPurchaseOrderEventAction;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceWorkflowNotificationService;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class SubmitSupplierInvoiceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordPurchaseOrderEventAction $recordPoEvent,
        private readonly InvoiceWorkflowNotificationService $invoiceNotifications,
    ) {}

    public function execute(User $user, Invoice $invoice, ?string $note = null): Invoice
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft->value, InvoiceStatus::Rejected->value, InvoiceStatus::BuyerReview->value], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft, buyer review, or rejected invoices can be submitted.'],
            ]);
        }

        $invoice->status = InvoiceStatus::Submitted->value;
        $invoice->submitted_at = now();
        $invoice->review_note = null;
        $invoice->reviewed_at = null;
        $invoice->reviewed_by_id = null;
        $invoice->save();

        $this->auditLogger->custom($invoice, 'invoice_submitted', [
            'actor_id' => $user->id,
            'actor_type' => 'supplier',
            'note' => $note,
        ]);

        if ($invoice->purchase_order_id !== null) {
            $invoice->loadMissing('purchaseOrder');
            $purchaseOrder = $invoice->purchaseOrder;

            if ($purchaseOrder !== null) {
                $this->recordPoEvent->execute(
                    $purchaseOrder,
                    'invoice_submitted',
                    sprintf('Invoice %s submitted by supplier', $invoice->invoice_number ?? $invoice->id),
                    null,
                    [
                        'invoice_id' => $invoice->getKey(),
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                    ],
                    $user,
                    now(),
                );
            }
        }

        $this->invoiceNotifications->notifyBuyerOfSubmission($invoice, $note);

        return $invoice;
    }
}
