<?php

namespace App\Actions\Invoicing;

use App\Actions\PurchaseOrder\RecordPurchaseOrderEventAction;
use App\Enums\InvoiceStatus;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\InvoiceWorkflowNotificationService;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewSupplierInvoiceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordPurchaseOrderEventAction $recordPoEvent,
        private readonly InvoiceWorkflowNotificationService $invoiceNotifications,
        private readonly DatabaseManager $db,
    ) {}

    public function approve(User $user, Invoice $invoice, ?string $note = null): Invoice
    {
        $allowed = [InvoiceStatus::Submitted->value, InvoiceStatus::BuyerReview->value];

        return $this->db->transaction(function () use ($user, $invoice, $note, $allowed): Invoice {
            $this->assertStatus($invoice, $allowed, 'approve');

            $invoice->status = InvoiceStatus::Approved->value;
            $invoice->reviewed_at = now();
            $invoice->reviewed_by_id = $user->id;
            $invoice->review_note = $note;
            $invoice->save();

            $context = $this->contextPayload($invoice, $user, [
                'note' => $note,
                'action' => 'approved',
            ]);

            $this->auditLogger->custom($invoice, 'invoice_approved', $context);
            $this->recordPurchaseOrderEvent($invoice, 'invoice_approved', 'Invoice approved', $user, $context);

            $invoice->refresh();

            $this->invoiceNotifications->notifySupplierApproved($invoice, $note);

            return $invoice;
        });
    }

    public function reject(User $user, Invoice $invoice, string $note): Invoice
    {
        $allowed = [InvoiceStatus::Submitted->value, InvoiceStatus::BuyerReview->value];

        return $this->db->transaction(function () use ($user, $invoice, $note, $allowed): Invoice {
            $this->assertStatus($invoice, $allowed, 'reject');

            $invoice->status = InvoiceStatus::Rejected->value;
            $invoice->reviewed_at = now();
            $invoice->reviewed_by_id = $user->id;
            $invoice->review_note = $note;
            $invoice->payment_reference = null;
            $invoice->save();

            $context = $this->contextPayload($invoice, $user, [
                'note' => $note,
                'action' => 'rejected',
            ]);

            $this->auditLogger->custom($invoice, 'invoice_rejected', $context);
            $this->recordPurchaseOrderEvent($invoice, 'invoice_rejected', 'Invoice rejected', $user, $context);

            $invoice->refresh();

            $this->invoiceNotifications->notifySupplierRejected($invoice, $note);

            return $invoice;
        });
    }

    public function requestChanges(User $user, Invoice $invoice, string $note): Invoice
    {
        $allowed = [InvoiceStatus::Submitted->value, InvoiceStatus::BuyerReview->value];

        return $this->db->transaction(function () use ($user, $invoice, $note, $allowed): Invoice {
            $this->assertStatus($invoice, $allowed, 'request changes');

            $invoice->status = InvoiceStatus::BuyerReview->value;
            $invoice->reviewed_at = now();
            $invoice->reviewed_by_id = $user->id;
            $invoice->review_note = $note;
            $invoice->save();

            $context = $this->contextPayload($invoice, $user, [
                'note' => $note,
                'action' => 'buyer_review',
            ]);

            $this->auditLogger->custom($invoice, 'invoice_review_feedback', $context);
            $this->recordPurchaseOrderEvent($invoice, 'invoice_review_feedback', 'Invoice requires changes', $user, $context);

            $invoice->refresh();

            $this->invoiceNotifications->notifySupplierReviewFeedback($invoice, $note);

            return $invoice;
        });
    }

    public function markPaid(
        User $user,
        Invoice $invoice,
        string $paymentReference,
        ?string $note = null,
        ?float $paymentAmount = null,
        ?string $currencyOverride = null,
        ?string $paymentMethod = null,
        ?CarbonInterface $paidAt = null,
    ): Invoice
    {
        $allowed = [InvoiceStatus::Approved->value];

        return $this->db->transaction(function () use ($user, $invoice, $paymentReference, $note, $allowed, $paymentAmount, $currencyOverride, $paymentMethod, $paidAt): Invoice {
            $this->assertStatus($invoice, $allowed, 'mark paid');

            $invoice->status = InvoiceStatus::Paid->value;
            $invoice->payment_reference = $paymentReference;
            $invoice->reviewed_at = now();
            $invoice->reviewed_by_id = $user->id;
            $invoice->review_note = $note;
            $invoice->save();

            $payment = $this->recordPayment(
                $invoice,
                $user,
                $paymentReference,
                $paymentMethod,
                $paymentAmount,
                $currencyOverride,
                $note,
                $paidAt
            );

            $context = $this->contextPayload($invoice, $user, [
                'note' => $note,
                'payment_reference' => $paymentReference,
                'payment_amount_minor' => $payment->amount_minor,
                'action' => 'paid',
            ]);

            $this->auditLogger->custom($invoice, 'invoice_marked_paid', $context);
            $this->recordPurchaseOrderEvent($invoice, 'invoice_marked_paid', 'Invoice marked as paid', $user, $context);

            $invoice->refresh();

            $this->invoiceNotifications->notifySupplierPaid($invoice, $paymentReference, $note);

            return $invoice;
        });
    }

    private function assertStatus(Invoice $invoice, array $allowedStatuses, string $action): void
    {
        if (in_array($invoice->status, $allowedStatuses, true)) {
            return;
        }

        $friendly = implode(', ', array_map(static fn (string $status): string => Str::title(str_replace('_', ' ', $status)), $allowedStatuses));

        throw ValidationException::withMessages([
            'status' => [sprintf('Cannot %s invoice while it is %s. Allowed states: %s.', $action, $invoice->status, $friendly)],
        ]);
    }

    private function recordPurchaseOrderEvent(Invoice $invoice, string $event, string $message, User $user, array $context): void
    {
        $purchaseOrder = $this->resolvePurchaseOrder($invoice);

        if ($purchaseOrder === null) {
            return;
        }

        $this->recordPoEvent->execute(
            $purchaseOrder,
            $event,
            $message,
            null,
            array_merge($context, [
                'invoice_id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
            ]),
            $user,
            now(),
        );
    }

    private function resolvePurchaseOrder(Invoice $invoice): ?PurchaseOrder
    {
        if (! $invoice->relationLoaded('purchaseOrder')) {
            $invoice->load('purchaseOrder');
        }

        return $invoice->purchaseOrder;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function contextPayload(Invoice $invoice, User $user, array $extra = []): array
    {
        $base = [
            'invoice_id' => $invoice->getKey(),
            'status' => $invoice->status,
            'actor_id' => $user->getKey(),
            'actor_name' => $user->name,
            'actor_type' => 'buyer',
        ];

        $filteredExtra = array_filter($extra, static fn ($value) => $value !== null && $value !== '');

        return array_filter(array_merge($base, $filteredExtra), static fn ($value) => $value !== null && $value !== '');
    }

    private function recordPayment(
        Invoice $invoice,
        User $user,
        string $paymentReference,
        ?string $paymentMethod,
        ?float $paymentAmount,
        ?string $currencyOverride,
        ?string $note,
        ?CarbonInterface $paidAt
    ): InvoicePayment {
        $currency = strtoupper($currencyOverride ?? $invoice->currency ?? 'USD');
        $minorUnit = $this->resolveMinorUnit($currency);

        if ($paymentAmount !== null) {
            $money = Money::fromDecimal($paymentAmount, $currency, $minorUnit);
            $amountMinor = $money->amountMinor();
            $amountDecimal = $money->toDecimal($minorUnit);
        } elseif ($invoice->total_minor !== null) {
            $amountMinor = (int) $invoice->total_minor;
            $amountDecimal = Money::fromMinor($amountMinor, $currency)->toDecimal($minorUnit);
        } elseif ($invoice->total !== null) {
            $money = Money::fromDecimal((float) $invoice->total, $currency, $minorUnit);
            $amountMinor = $money->amountMinor();
            $amountDecimal = $money->toDecimal($minorUnit);
        } else {
            $amountMinor = 0;
            $amountDecimal = Money::fromMinor(0, $currency)->toDecimal($minorUnit);
        }

        return InvoicePayment::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->getKey(),
            'created_by_id' => $user->getKey(),
            'amount' => $amountDecimal,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'paid_at' => $paidAt?->toDateTimeString() ?? now(),
            'payment_reference' => $paymentReference,
            'payment_method' => $paymentMethod,
            'note' => $note,
        ]);
    }

    private function resolveMinorUnit(string $currency): int
    {
        $minor = Currency::query()
            ->where('code', strtoupper($currency))
            ->value('minor_unit');

        return $minor !== null ? (int) $minor : 2;
    }
}
