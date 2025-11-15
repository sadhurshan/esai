<?php

namespace App\Services;

use App\Enums\CreditNoteStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\MoneyRoundRule;
use App\Models\CompanyMoneySetting;
use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use App\Support\Money\Money;
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

        $currency = strtoupper((string) ($invoice->currency ?? ''));

        if ($currency === '' && $purchaseOrder->currency !== null) {
            $currency = strtoupper((string) $purchaseOrder->currency);
        }

        if ($currency === '') {
            throw ValidationException::withMessages([
                'currency' => ['Invoice currency context is required.'],
            ]);
        }

        if (isset($payload['currency']) && strtoupper((string) $payload['currency']) !== $currency) {
            throw ValidationException::withMessages([
                'currency' => ['Credit note currency must match the invoice currency.'],
            ]);
        }

        $minorUnit = $this->resolveMinorUnit($currency);
        $roundRule = $this->resolveRoundRule($companyId);

        $invoiceTotal = Money::fromDecimal((float) $invoice->total, $currency, $minorUnit, $roundRule);

        $amountMinor = $this->resolveAmountMinor($payload['amount'] ?? null, $payload['amount_minor'] ?? null, $currency, $minorUnit, $roundRule);

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than zero.'],
            ]);
        }

        if ($amountMinor > $invoiceTotal->amountMinor()) {
            throw ValidationException::withMessages([
                'amount' => ['Credit amount cannot exceed the invoice total.'],
            ]);
        }

        $amountMoney = Money::fromMinor($amountMinor, $currency);

        return DB::transaction(function () use (
            $invoice,
            $purchaseOrder,
            $payload,
            $creator,
            $attachments,
            $companyId,
            $amountMoney,
            $amountMinor,
            $currency,
            $minorUnit,
            $grnId
        ): CreditNote {
            $creditNote = CreditNote::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'purchase_order_id' => $purchaseOrder->id,
                'grn_id' => $grnId,
                'credit_number' => $this->generateCreditNumber($companyId),
                'currency' => $currency,
                'amount' => $amountMoney->toDecimal($minorUnit),
                'amount_minor' => $amountMinor,
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

            return $this->refreshDetail($creditNote);
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

            return $this->refreshDetail($creditNote);
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

                return $this->refreshDetail($creditNote);
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

            $invoiceCurrency = strtoupper($invoice->currency ?? $creditNote->currency ?? 'USD');
            $minorUnit = $this->resolveMinorUnit($invoiceCurrency);
            $roundRule = $this->resolveRoundRule((int) $creditNote->company_id);

            $creditAmountMinor = $creditNote->amount_minor ?? Money::fromDecimal((float) $creditNote->amount, $invoiceCurrency, $minorUnit, $roundRule)->amountMinor();

            $invoiceTotalMinor = Money::fromDecimal((float) $invoice->total, $invoiceCurrency, $minorUnit, $roundRule)->amountMinor();

            $invoiceTaxMinor = $invoice->tax_amount !== null
                ? Money::fromDecimal((float) $invoice->tax_amount, $invoiceCurrency, $minorUnit, $roundRule)->amountMinor()
                : 0;

            $invoiceSubtotalMinor = $invoice->subtotal !== null
                ? Money::fromDecimal((float) $invoice->subtotal, $invoiceCurrency, $minorUnit, $roundRule)->amountMinor()
                : max($invoiceTotalMinor - $invoiceTaxMinor, 0);

            $creditMinor = min($creditAmountMinor, $invoiceTotalMinor);
            $remaining = $creditMinor;

            $taxReduction = min($remaining, $invoiceTaxMinor);
            $newTaxMinor = max($invoiceTaxMinor - $taxReduction, 0);
            $remaining -= $taxReduction;

            $newSubtotalMinor = max($invoiceSubtotalMinor - $remaining, 0);
            $newTotalMinor = max($invoiceTotalMinor - $creditMinor, 0);

            $invoiceBefore = [
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'total' => $invoice->total,
            ];

            $invoice->currency = $invoiceCurrency;
            $invoice->subtotal = Money::fromMinor($newSubtotalMinor, $invoiceCurrency)->toDecimal($minorUnit);
            $invoice->tax_amount = Money::fromMinor($newTaxMinor, $invoiceCurrency)->toDecimal($minorUnit);
            $invoice->total = Money::fromMinor($newTotalMinor, $invoiceCurrency)->toDecimal($minorUnit);
            $invoice->save();

            $this->auditLogger->updated($invoice, $invoiceBefore, [
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'total' => $invoice->total,
            ]);

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

            return $this->refreshDetail($creditNote);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function updateCreditNoteLines(CreditNote $creditNote, array $lines, User $actor): CreditNote
    {
        if ($creditNote->status !== CreditNoteStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Only draft credit notes can be edited.'],
            ]);
        }

        if ($creditNote->company_id === null) {
            throw ValidationException::withMessages([
                'credit_note_id' => ['Credit note company context is required.'],
            ]);
        }

        if ($creditNote->invoice_id === null) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Credit note invoice context is required.'],
            ]);
        }

        if (count($lines) === 0) {
            throw ValidationException::withMessages([
                'lines' => ['At least one credit line is required.'],
            ]);
        }

        /** @var Invoice|null $invoice */
        $invoice = $creditNote->invoice()->with('lines')->first();

        if (! $invoice instanceof Invoice) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Invoice not found for this credit note.'],
            ]);
        }

        $invoiceLines = $invoice->lines->keyBy('id');

        if ($invoiceLines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => ['Invoice has no lines available for credit.'],
            ]);
        }

        $normalized = [];

        foreach ($lines as $index => $line) {
            $invoiceLineId = (int) ($line['invoice_line_id'] ?? $line['invoiceLineId'] ?? 0);
            $quantity = $line['qty_to_credit'] ?? $line['qtyToCredit'] ?? null;
            $qtyToCredit = is_numeric($quantity) ? (float) $quantity : null;

            if ($invoiceLineId <= 0) {
                throw ValidationException::withMessages([
                    "lines.$index.invoice_line_id" => ['Invoice line id is required.'],
                ]);
            }

            if ($qtyToCredit === null || $qtyToCredit < 0) {
                throw ValidationException::withMessages([
                    "lines.$index.qty_to_credit" => ['Quantity to credit must be zero or greater.'],
                ]);
            }

            if (! $invoiceLines->has($invoiceLineId)) {
                throw ValidationException::withMessages([
                    "lines.$index.invoice_line_id" => ['Line does not belong to the credit note invoice.'],
                ]);
            }

            if (array_key_exists($invoiceLineId, $normalized)) {
                throw ValidationException::withMessages([
                    "lines.$index.invoice_line_id" => ['Duplicate invoice line detected.'],
                ]);
            }

            $normalized[$invoiceLineId] = [
                'qty_to_credit' => $qtyToCredit,
                'description' => $line['description'] ?? null,
                'uom' => $line['uom'] ?? null,
            ];
        }

        $previousCredits = $this->previouslyCreditedQuantities($creditNote, array_keys($normalized));

        return DB::transaction(function () use ($creditNote, $invoice, $invoiceLines, $normalized, $previousCredits): CreditNote {
            $currency = strtoupper($invoice->currency ?? $creditNote->currency ?? 'USD');
            $minorUnit = $this->resolveMinorUnit($currency);
            $totalMinor = 0;
            $before = $creditNote->only(['amount', 'amount_minor', 'currency']);

            foreach ($normalized as $invoiceLineId => $linePayload) {
                /** @var InvoiceLine $invoiceLine */
                $invoiceLine = $invoiceLines->get($invoiceLineId);

                $qtyInvoiced = (float) ($invoiceLine->quantity ?? 0);
                $alreadyCredited = (float) ($previousCredits[$invoiceLineId] ?? 0);
                $remaining = max($qtyInvoiced - $alreadyCredited, 0);
                $requested = (float) $linePayload['qty_to_credit'];

                if ($requested > $remaining + 0.0001) {
                    throw ValidationException::withMessages([
                        'lines' => [sprintf('Cannot credit more than remaining quantity for invoice line %s.', $invoiceLineId)],
                    ]);
                }

                $unitPriceMinor = $this->resolveInvoiceLineUnitPriceMinor($invoiceLine, $currency, $minorUnit);
                $lineTotalMinor = (int) max(0, round($requested * $unitPriceMinor));

                $creditNote->lines()->updateOrCreate(
                    [
                        'invoice_line_id' => $invoiceLineId,
                    ],
                    [
                        'qty_to_credit' => $requested,
                        'qty_invoiced' => $qtyInvoiced,
                        'unit_price_minor' => $unitPriceMinor,
                        'line_total_minor' => $lineTotalMinor,
                        'currency' => $invoiceLine->currency ?? $currency,
                        'uom' => $linePayload['uom'] ?? $invoiceLine->uom,
                        'description' => $linePayload['description'] ?? $invoiceLine->description,
                    ]
                );

                $totalMinor += $lineTotalMinor;
            }

            $creditNote->lines()->whereNotIn('invoice_line_id', array_keys($normalized))->delete();

            if ($totalMinor <= 0) {
                throw ValidationException::withMessages([
                    'lines' => ['At least one line must have a non-zero total.'],
                ]);
            }

            $creditNote->currency = $currency;
            $creditNote->amount_minor = $totalMinor;
            $creditNote->amount = Money::fromMinor($totalMinor, $currency)->toDecimal($minorUnit);
            $creditNote->save();

            $this->auditLogger->updated($creditNote, $before, [
                'amount' => $creditNote->amount,
                'amount_minor' => $creditNote->amount_minor,
                'currency' => $creditNote->currency,
            ]);

            return $this->refreshDetail($creditNote);
        });
    }

    private function refreshDetail(CreditNote $creditNote): CreditNote
    {
        $reloaded = $creditNote->fresh();

        if (! $reloaded instanceof CreditNote) {
            return $creditNote;
        }

        return $this->loadDetail($reloaded);
    }

    public function loadDetail(CreditNote $creditNote): CreditNote
    {
        $creditNote->load([
            'invoice' => function ($query): void {
                $query->with(['lines', 'supplier', 'purchaseOrder']);
            },
            'purchaseOrder',
            'goodsReceiptNote',
            'documents',
            'lines.invoiceLine',
        ]);

        $this->attachLineContext($creditNote);

        return $creditNote;
    }

    private function attachLineContext(CreditNote $creditNote): void
    {
        if (! $creditNote->relationLoaded('lines')) {
            return;
        }

        $lineIds = $creditNote->lines->pluck('invoice_line_id')->filter()->unique()->all();

        if ($lineIds === []) {
            return;
        }

        $previousCredits = $this->previouslyCreditedQuantities($creditNote, $lineIds);

        foreach ($creditNote->lines as $line) {
            $line->setAttribute('previously_credited_qty', $previousCredits[$line->invoice_line_id] ?? 0);
        }
    }

    /**
     * @param array<int, int> $invoiceLineIds
     * @return array<int, float>
     */
    private function previouslyCreditedQuantities(CreditNote $creditNote, array $invoiceLineIds): array
    {
        if ($creditNote->company_id === null || $creditNote->invoice_id === null || $invoiceLineIds === []) {
            return [];
        }

        return CreditNoteLine::query()
            ->selectRaw('invoice_line_id, SUM(qty_to_credit) as qty_total')
            ->join('credit_notes', 'credit_note_lines.credit_note_id', '=', 'credit_notes.id')
            ->where('credit_notes.company_id', $creditNote->company_id)
            ->where('credit_notes.invoice_id', $creditNote->invoice_id)
            ->where('credit_notes.id', '!=', $creditNote->id)
            ->where('credit_notes.status', '!=', CreditNoteStatus::Rejected->value)
            ->whereIn('credit_note_lines.invoice_line_id', $invoiceLineIds)
            ->groupBy('credit_note_lines.invoice_line_id')
            ->pluck('qty_total', 'invoice_line_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function resolveInvoiceLineUnitPriceMinor(InvoiceLine $invoiceLine, string $currency, int $minorUnit): int
    {
        if ($invoiceLine->unit_price_minor !== null) {
            return (int) $invoiceLine->unit_price_minor;
        }

        $resolvedCurrency = $invoiceLine->currency ?? $currency;
        $resolvedMinorUnit = $resolvedCurrency === $currency ? $minorUnit : $this->resolveMinorUnit($resolvedCurrency);

        return Money::fromDecimal((float) $invoiceLine->unit_price, $resolvedCurrency, $resolvedMinorUnit)->amountMinor();
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

    private function resolveAmountMinor(mixed $amount, mixed $amountMinor, string $currency, int $minorUnit, MoneyRoundRule $roundRule): int
    {
        if ($amountMinor !== null && is_numeric($amountMinor)) {
            return (int) $amountMinor;
        }

        if ($amount === null || ! is_numeric($amount)) {
            return 0;
        }

        return Money::fromDecimal((float) $amount, $currency, $minorUnit, $roundRule)->amountMinor();
    }

    private function resolveMinorUnit(string $currency): int
    {
        $record = Currency::query()->where('code', strtoupper($currency))->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'currency' => [sprintf('Currency %s is not configured.', strtoupper($currency))],
            ]);
        }

        return (int) $record->minor_unit;
    }

    private function resolveRoundRule(int $companyId): MoneyRoundRule
    {
        $setting = CompanyMoneySetting::query()->where('company_id', $companyId)->first();

        if ($setting === null || $setting->price_round_rule === null) {
            return MoneyRoundRule::HalfUp;
        }

        return MoneyRoundRule::from($setting->price_round_rule);
    }
}
