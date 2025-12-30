<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class PaymentDraftConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly ValidationFactory $validator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{entity: InvoicePayment}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_PAYMENT_DRAFT);
        $payload = $result['payload'];
        $validated = $this->validatePayload($payload);

        $invoice = $this->resolveInvoice($draft, $validated['invoice_id'], $user->company_id);
        $companyId = (int) $invoice->company_id;

        if ($companyId <= 0) {
            throw $this->validationError('invoice_id', 'Invoice is missing a company assignment.');
        }

        if ($user->company_id !== null && (int) $user->company_id !== $companyId) {
            throw $this->validationError('invoice_id', 'Invoice does not belong to your company.');
        }

        $currency = strtoupper($validated['currency']);
        $minorUnit = $this->resolveMinorUnit($currency);
        $money = Money::fromDecimal($validated['amount'], $currency, $minorUnit);

        $paidAt = $validated['scheduled_date'] !== null
            ? Carbon::parse($validated['scheduled_date'])
            : now();

        $reference = $validated['reference'] ?? $this->generateReference($invoice, $paidAt);
        $notes = $validated['notes'] ?? 'Payment recorded from Copilot workflow.';
        $paymentMethod = strtolower($validated['payment_method']);

        $payment = $this->db->transaction(function () use ($invoice, $user, $money, $currency, $paidAt, $reference, $notes, $minorUnit, $paymentMethod): InvoicePayment {
            $payment = InvoicePayment::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'created_by_id' => $user->id,
                'amount' => $money->toDecimal($minorUnit),
                'amount_minor' => $money->amountMinor(),
                'currency' => $currency,
                'paid_at' => $paidAt,
                'payment_reference' => $reference,
                'payment_method' => $paymentMethod,
                'note' => $notes,
            ]);

            if ($invoice->payment_reference === null) {
                $invoice->payment_reference = $reference;
                $invoice->save();
            }

            return $payment->fresh();
        });

        $this->auditLogger->custom($invoice, 'invoice_payment_captured', [
            'source' => 'copilot',
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'currency' => $payment->currency,
            'amount_minor' => $payment->amount_minor,
        ]);

        $draft->forceFill([
            'entity_type' => $payment->getMorphClass(),
            'entity_id' => $payment->id,
        ])->save();

        return ['entity' => $payment];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     invoice_id: string,
     *     amount: float,
     *     currency: string,
     *     payment_method: string,
     *     scheduled_date: ?string,
     *     reference: ?string,
     *     notes: ?string
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'invoice_id' => $payload['invoice_id'] ?? null,
                'amount' => $payload['amount'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'payment_method' => $payload['payment_method'] ?? null,
                'scheduled_date' => $payload['scheduled_date'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            [
                'invoice_id' => ['required', 'string', 'max:120'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'currency' => ['required', 'string', 'size:3'],
                'payment_method' => ['required', 'string', 'max:60'],
                'scheduled_date' => ['nullable', 'date'],
                'reference' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'invoice_id' => (string) ($payload['invoice_id'] ?? ''),
            'amount' => isset($payload['amount']) ? (float) $payload['amount'] : 0.0,
            'currency' => strtoupper((string) ($payload['currency'] ?? 'USD')),
            'payment_method' => (string) ($payload['payment_method'] ?? 'ach'),
            'scheduled_date' => $this->stringValue($payload['scheduled_date'] ?? null),
            'reference' => $this->stringValue($payload['reference'] ?? null),
            'notes' => $this->stringValue($payload['notes'] ?? null),
        ];
    }

    private function resolveInvoice(AiActionDraft $draft, string $identifier, ?int $companyIdHint): Invoice
    {
        $context = $this->entityContext($draft);

        if ($context['entity_id'] !== null && $this->isInvoiceContext($context['entity_type'])) {
            $invoice = $this->invoiceQuery($companyIdHint)
                ->whereKey($context['entity_id'])
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        if (is_numeric($identifier)) {
            $invoice = $this->invoiceQuery($companyIdHint)
                ->whereKey((int) $identifier)
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        $invoice = $this->invoiceQuery($companyIdHint)
            ->where('invoice_number', $identifier)
            ->first();

        if (! $invoice instanceof Invoice) {
            throw $this->validationError('invoice_id', 'Invoice not found for this company.');
        }

        return $invoice;
    }

    private function invoiceQuery(?int $companyIdHint)
    {
        $query = Invoice::query();

        if ($companyIdHint !== null) {
            $query->forCompany($companyIdHint);
        }

        return $query;
    }

    private function isInvoiceContext(?string $entityType): bool
    {
        if ($entityType === null) {
            return false;
        }

        return str_contains(strtolower($entityType), 'invoice');
    }

    private function resolveMinorUnit(string $currency): int
    {
        $record = Currency::query()
            ->where('code', strtoupper($currency))
            ->value('minor_unit');

        return $record !== null ? (int) $record : 2;
    }

    private function generateReference(Invoice $invoice, Carbon $paidAt): string
    {
        $prefix = $invoice->invoice_number ?? sprintf('INV-%d', $invoice->id ?? 0);

        return sprintf('%s-%s', $prefix, $paidAt->format('Ymd')); 
    }
}
