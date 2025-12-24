<?php

namespace App\Services\Ai\Converters;

use App\Actions\Invoicing\ReviewSupplierInvoiceAction;
use App\Models\AiActionDraft;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceApprovalConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly ReviewSupplierInvoiceAction $reviewSupplierInvoiceAction,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @return array{entity:mixed}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_APPROVE_INVOICE);
        $payload = $this->validatePayload($result['payload']);
        $companyId = $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['User is missing a company context.'],
            ]);
        }

        $invoice = $this->resolveInvoice($draft, $payload['invoice_id'], $companyId);
        $paidAt = $payload['paid_at'] !== null ? CarbonImmutable::parse($payload['paid_at']) : null;

        $invoice = $this->reviewSupplierInvoiceAction->markPaid(
            $user,
            $invoice,
            $payload['payment_reference'],
            $payload['note'],
            $payload['payment_amount'],
            $payload['payment_currency'],
            $payload['payment_method'],
            $paidAt,
        );

        $draft->forceFill([
            'entity_type' => $invoice->getMorphClass(),
            'entity_id' => $invoice->getKey(),
        ])->save();

        return ['entity' => $invoice];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     invoice_id: string,
     *     payment_reference: string,
     *     note: ?string,
     *     payment_amount: ?float,
     *     payment_currency: ?string,
     *     payment_method: ?string,
     *     paid_at: ?string,
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'invoice_id' => $payload['invoice_id'] ?? null,
                'payment_reference' => $payload['payment_reference'] ?? null,
                'note' => $payload['note'] ?? null,
                'payment_amount' => $payload['payment_amount'] ?? null,
                'payment_currency' => $payload['payment_currency'] ?? null,
                'payment_method' => $payload['payment_method'] ?? null,
                'paid_at' => $payload['paid_at'] ?? null,
            ],
            [
                'invoice_id' => ['required', 'string', 'max:191'],
                'payment_reference' => ['required', 'string', 'max:191'],
                'note' => ['nullable', 'string', 'max:1000'],
                'payment_amount' => ['nullable', 'numeric', 'min:0'],
                'payment_currency' => ['nullable', 'string', 'size:3'],
                'payment_method' => ['nullable', 'string', 'max:120'],
                'paid_at' => ['nullable', 'date'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $currency = $this->stringValue($payload['payment_currency'] ?? null);

        return [
            'invoice_id' => (string) $payload['invoice_id'],
            'payment_reference' => (string) $payload['payment_reference'],
            'note' => $this->stringValue($payload['note'] ?? null),
            'payment_amount' => isset($payload['payment_amount']) ? (float) $payload['payment_amount'] : null,
            'payment_currency' => $currency !== null ? Str::upper($currency) : null,
            'payment_method' => $this->stringValue($payload['payment_method'] ?? null),
            'paid_at' => $this->stringValue($payload['paid_at'] ?? null),
        ];
    }

    private function resolveInvoice(AiActionDraft $draft, string $identifier, int $companyId): Invoice
    {
        $context = $this->entityContext($draft);

        if ($context['entity_id'] !== null && $this->isInvoiceContext($context['entity_type'])) {
            $invoice = Invoice::query()
                ->forCompany($companyId)
                ->whereKey($context['entity_id'])
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        if (is_numeric($identifier)) {
            $invoice = Invoice::query()
                ->forCompany($companyId)
                ->whereKey((int) $identifier)
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        $invoice = Invoice::query()
            ->forCompany($companyId)
            ->where('invoice_number', $identifier)
            ->first();

        if (! $invoice instanceof Invoice) {
            throw $this->validationError('invoice_id', 'Invoice not found for this company.');
        }

        return $invoice;
    }

    private function isInvoiceContext(?string $entityType): bool
    {
        if ($entityType === null) {
            return false;
        }

        $normalized = Str::lower($entityType);

        return in_array($normalized, ['invoice', 'supplier_invoice'], true);
    }
}
