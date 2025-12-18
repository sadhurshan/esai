<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\Admin\WebhookService;
use App\Support\Notifications\NotificationService;
use Illuminate\Support\Collection;

class InvoiceWorkflowNotificationService
{
    private const BUYER_ROLES = ['owner', 'buyer_admin', 'finance'];
    private const SUPPLIER_ROLES = ['owner', 'supplier_admin', 'supplier_estimator'];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly WebhookService $webhooks,
    ) {}

    public function notifyBuyerOfSubmission(Invoice $invoice, ?string $note = null): void
    {
        $this->ensureRelations($invoice);

        $recipients = $this->buyerRecipients($invoice);

        if ($recipients->isNotEmpty()) {
            $supplierName = $invoice->supplierCompany?->name ?? $invoice->supplier?->name ?? 'supplier';
            $poNumber = $invoice->purchaseOrder?->po_number ?? '#'.$invoice->purchase_order_id;
            $invoiceLabel = $invoice->invoice_number ?? '#'.$invoice->getKey();

            $title = sprintf('Invoice %s submitted by %s', $invoiceLabel, $supplierName);
            $body = sprintf(
                '%s submitted invoice %s for PO %s. Review the charges and approve or request changes.',
                $supplierName,
                $invoiceLabel,
                $poNumber,
            );

            $this->notifications->send(
                $recipients,
                'invoice_status_changed',
                $title,
                $body,
                Invoice::class,
                $invoice->getKey(),
                $this->baseMeta($invoice, [
                    'invoice_event' => 'supplier_submitted',
                    'note' => $note,
                ]),
            );
        }

        $this->emitWebhook($invoice->company, 'invoice.submitted', $this->baseMeta($invoice, [
            'actor' => 'supplier',
            'note' => $note,
        ]));
    }

    public function notifySupplierApproved(Invoice $invoice, ?string $note = null): void
    {
        $this->notifySupplier($invoice, 'invoice.approved', 'Invoice approved',
            sprintf('Your invoice %s has been approved.', $invoice->invoice_number ?? '#'.$invoice->getKey()),
            [
                'invoice_event' => 'approved',
                'note' => $note,
            ]
        );
    }

    public function notifySupplierRejected(Invoice $invoice, string $note): void
    {
        $this->notifySupplier($invoice, 'invoice.rejected', 'Invoice rejected',
            sprintf('Your invoice %s was rejected. Review the buyer note and resubmit.', $invoice->invoice_number ?? '#'.$invoice->getKey()),
            [
                'invoice_event' => 'rejected',
                'note' => $note,
            ]
        );
    }

    public function notifySupplierReviewFeedback(Invoice $invoice, string $note): void
    {
        $this->notifySupplier($invoice, 'invoice.review_feedback', 'Invoice requires changes',
            sprintf('Buyer requested updates for invoice %s. Review feedback and resubmit.', $invoice->invoice_number ?? '#'.$invoice->getKey()),
            [
                'invoice_event' => 'buyer_review',
                'note' => $note,
            ]
        );
    }

    public function notifySupplierPaid(Invoice $invoice, string $paymentReference, ?string $note = null): void
    {
        $this->notifySupplier($invoice, 'invoice.paid', 'Invoice paid',
            sprintf('Invoice %s has been marked as paid. Reference: %s.', $invoice->invoice_number ?? '#'.$invoice->getKey(), $paymentReference),
            [
                'invoice_event' => 'paid',
                'payment_reference' => $paymentReference,
                'note' => $note,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     */
    private function notifySupplier(Invoice $invoice, string $webhookEvent, string $title, string $body, array $extraMeta = []): void
    {
        $this->ensureRelations($invoice);

        $recipients = $this->supplierRecipients($invoice);

        if ($recipients->isNotEmpty()) {
            $this->notifications->send(
                $recipients,
                'invoice_status_changed',
                $title,
                $body,
                Invoice::class,
                $invoice->getKey(),
                $this->baseMeta($invoice, $extraMeta),
            );
        }

        $this->emitWebhook($invoice->supplierCompany, $webhookEvent, $this->baseMeta($invoice, array_merge([
            'actor' => 'buyer',
        ], $extraMeta)));
    }

    private function ensureRelations(Invoice $invoice): void
    {
        $invoice->loadMissing('company.users', 'supplierCompany.users', 'purchaseOrder', 'supplier');
    }

    private function buyerRecipients(Invoice $invoice): Collection
    {
        $company = $invoice->company;

        if ($company === null) {
            return collect();
        }

        return $company->users()
            ->whereIn('role', self::BUYER_ROLES)
            ->get();
    }

    private function supplierRecipients(Invoice $invoice): Collection
    {
        $company = $invoice->supplierCompany;

        if ($company === null) {
            return collect();
        }

        return $company->users()
            ->whereIn('role', self::SUPPLIER_ROLES)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function baseMeta(Invoice $invoice, array $extra = []): array
    {
        $invoice->loadMissing('purchaseOrder');

        $payload = [
            'invoice_id' => $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'purchase_order_id' => $invoice->purchase_order_id,
            'po_number' => $invoice->purchaseOrder?->po_number,
            'supplier_company_id' => $invoice->supplier_company_id,
            'currency' => $invoice->currency,
            'total' => $invoice->total,
        ];

        $merged = array_merge($payload, $extra);

        return array_filter($merged, static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitWebhook(?Company $company, string $event, array $payload): void
    {
        if ($company === null || ! $company->exists) {
            return;
        }

        $this->webhooks->emit($company, $event, $payload);
    }
}
