<?php

namespace App\Actions\Invoicing;

use App\Actions\PurchaseOrder\RecordPurchaseOrderEventAction;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Documents\DocumentStorer;
use App\Support\Money\Money;
use App\Support\PurchaseOrders\PurchaseOrderSupplierResolver;
use App\Services\TotalsCalculator;
use App\Services\LineTaxSyncService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateInvoiceAction
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync,
        private readonly RecordPurchaseOrderEventAction $recordPoEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{company_id?:int,supplier_company_id?:int|null,status?:string,created_by_type?:string,created_by_id?:int|null}|null  $context
     */
    public function execute(User $user, PurchaseOrder $purchaseOrder, array $payload, ?array $context = null): Invoice
    {
        $context ??= [];

        $companyId = $context['company_id'] ?? $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['User company context missing.'],
            ]);
        }

        if ((int) $purchaseOrder->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order not found for this company.'],
            ]);
        }

        $supplierId = $this->resolveSupplierId($purchaseOrder, $payload);

        if ($supplierId === null) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Unable to resolve supplier for this invoice.'],
            ]);
        }

        $supplierCompanyId = $context['supplier_company_id'] ?? $this->resolveSupplierCompanyId($purchaseOrder);

        $statusValue = $context['status'] ?? InvoiceStatus::Draft->value;
        $status = InvoiceStatus::tryFrom($statusValue);

        if ($status === null) {
            throw ValidationException::withMessages([
                'status' => ['Invalid invoice status provided.'],
            ]);
        }

        $createdByType = $context['created_by_type'] ?? 'buyer';

        if (! in_array($createdByType, ['buyer', 'supplier'], true)) {
            throw ValidationException::withMessages([
                'created_by_type' => ['Created by type must be buyer or supplier.'],
            ]);
        }

        $createdById = $context['created_by_id'] ?? $user->id;

        $purchaseOrder->loadMissing(['lines.invoiceLines']);

        /** @var Collection<int, array<string, mixed>> $linesPayload */
        $linesPayload = collect($payload['lines'] ?? []);

        if ($linesPayload->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => ['At least one invoice line is required.'],
            ]);
        }

        $invoiceNumber = $payload['invoice_number'] ?? $this->generateInvoiceNumber($companyId);
        $invoiceDate = $payload['invoice_date'] ?? now()->toDateString();
        $dueDate = $payload['due_date'] ?? null;
        $currency = $payload['currency'] ?? $purchaseOrder->currency ?? 'USD';

        /** @var UploadedFile|null $document */
        $document = $payload['document'] ?? null;

        return $this->db->transaction(function () use (
            $companyId,
            $user,
            $purchaseOrder,
            $supplierId,
            $supplierCompanyId,
            $invoiceNumber,
            $invoiceDate,
            $dueDate,
            $currency,
            $linesPayload,
            $document,
            $status,
            $createdByType,
            $createdById,
        ): Invoice {
            $resolvedLines = $this->resolveLines($purchaseOrder, $linesPayload);

            $calculation = $this->totalsCalculator->calculate(
                $companyId,
                $currency,
                $resolvedLines->map(fn (array $line): array => [
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_code_ids' => $line['tax_code_ids'],
                ])->values()->all()
            );

            $minorUnit = $calculation['minor_unit'];

            $subtotalMinor = $calculation['totals']['subtotal_minor'];
            $taxMinor = $calculation['totals']['tax_total_minor'];
            $totalMinor = $calculation['totals']['grand_total_minor'];

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplierId,
                'supplier_company_id' => $supplierCompanyId,
                'invoice_number' => $invoiceNumber,
                'currency' => strtoupper($currency),
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'subtotal' => $this->formatMinor($subtotalMinor, $currency, $minorUnit),
                'tax_amount' => $this->formatMinor($taxMinor, $currency, $minorUnit),
                'total' => $this->formatMinor($totalMinor, $currency, $minorUnit),
                'subtotal_minor' => $subtotalMinor,
                'tax_minor' => $taxMinor,
                'total_minor' => $totalMinor,
                'status' => $status->value,
                'created_by_type' => $createdByType,
                'created_by_id' => $createdById,
            ]);

            $lineResults = collect($calculation['lines'])->keyBy('index');

            $resolvedLines->each(function (array $line, int $index) use ($invoice, $currency, $minorUnit, $lineResults, $companyId): void {
                $result = $lineResults->get($index);

                if ($result === null) {
                    throw ValidationException::withMessages([
                        'lines' => ['Unable to calculate totals for one or more lines.'],
                    ]);
                }

                $unitPrice = $this->formatMinor($result['unit_price_minor'], $currency, $minorUnit);

                $invoiceLine = InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'po_line_id' => $line['po_line_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'uom' => $line['uom'],
                    'currency' => strtoupper($currency),
                    'unit_price' => $unitPrice,
                    'unit_price_minor' => $result['unit_price_minor'],
                    'line_total_minor' => $result['grand_total_minor'],
                ]);

                $this->lineTaxSync->sync($invoiceLine, $companyId, $result['taxes']);
            });

            if ($document instanceof UploadedFile) {
                $stored = $this->documentStorer->store(
                    $user,
                    $document,
                    'financial',
                    $companyId,
                    $invoice->getMorphClass(),
                    $invoice->id,
                    [
                        'kind' => 'invoice',
                        'visibility' => 'company',
                        'meta' => ['context' => 'invoice_attachment'],
                    ]
                );

                $invoice->document_id = $stored->id;
                $invoice->save();
            }

            $company = Company::query()
                ->whereKey($companyId)
                ->lockForUpdate()
                ->first();

            if ($company !== null) {
                $company->increment('invoices_monthly_used');
            }

            $invoice->load(['lines.taxes.taxCode', 'document']);

            $this->auditLogger->created($invoice, ['user_id' => $user->id]);

            $this->recordPoEvent->execute(
                $purchaseOrder,
                'invoice_created',
                sprintf('Invoice %s created', $invoice->invoice_number),
                null,
                [
                    'invoice_id' => $invoice->getKey(),
                    'invoice_number' => $invoice->invoice_number,
                    'total_minor' => $calculation['totals']['grand_total_minor'],
                ],
                $user,
                now(),
            );

            return $invoice;
        });
    }

    private function resolveSupplierId(PurchaseOrder $purchaseOrder, array $payload): ?int
    {
        if (isset($payload['supplier_id'])) {
            $supplierId = (int) $payload['supplier_id'];

            $exists = CompanyContext::bypass(static function () use ($supplierId): bool {
                return Supplier::query()
                    ->whereKey($supplierId)
                    ->exists();
            });

            return $exists ? $supplierId : null;
        }

        if ($purchaseOrder->supplier_id !== null) {
            $supplierExists = CompanyContext::bypass(function () use ($purchaseOrder): bool {
                return Supplier::query()
                    ->whereKey($purchaseOrder->supplier_id)
                    ->exists();
            });

            if ($supplierExists) {
                return (int) $purchaseOrder->supplier_id;
            }
        }

        $purchaseOrder->loadMissing('quote');

        if ($purchaseOrder->quote?->supplier_id) {
            return (int) $purchaseOrder->quote->supplier_id;
        }

        return null;
    }

    private function resolveSupplierCompanyId(PurchaseOrder $purchaseOrder): ?int
    {
        return PurchaseOrderSupplierResolver::resolveSupplierCompanyId($purchaseOrder);
    }

    private function generateInvoiceNumber(int $companyId): string
    {
        $date = now()->format('ymd');

        do {
            $suffix = Str::upper(Str::random(4));
            $number = sprintf('INV-%s-%s', $date, $suffix);
            $exists = Invoice::query()
                ->where('company_id', $companyId)
                ->where('invoice_number', $number)
                ->exists();
        } while ($exists);

        return $number;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveLines(PurchaseOrder $purchaseOrder, Collection $lines): Collection
    {
        $purchaseOrder->loadMissing('lines');

        $resolved = $lines->map(function (array $line) use ($purchaseOrder): array {
            $poLineId = (int) ($line['po_line_id'] ?? 0);

            /** @var PurchaseOrderLine|null $poLine */
            $poLine = $purchaseOrder->lines->firstWhere('id', $poLineId);

            if ($poLine === null) {
                throw ValidationException::withMessages([
                    'lines' => ["Purchase order line {$poLineId} is invalid for this purchase order."],
                ]);
            }

            $poLine->loadMissing('invoiceLines');

            $quantity = isset($line['quantity']) ? (int) $line['quantity'] : (int) $poLine->quantity;
            $unitPrice = isset($line['unit_price']) ? (float) $line['unit_price'] : (float) $poLine->unit_price;

            return [
                'po_line_id' => $poLineId,
                'description' => $line['description'] ?? $poLine->description,
                'quantity' => $quantity,
                'uom' => $line['uom'] ?? $poLine->uom,
                'unit_price' => $unitPrice,
                'tax_code_ids' => array_values(array_filter(
                    array_map('intval', $line['tax_code_ids'] ?? []),
                    static fn (int $value) => $value > 0
                )),
            ];
        });

        $resolved
            ->groupBy('po_line_id')
            ->each(function (Collection $group, int $poLineId) use ($purchaseOrder): void {
                /** @var PurchaseOrderLine|null $poLine */
                $poLine = $purchaseOrder->lines->firstWhere('id', $poLineId);

                if ($poLine === null) {
                    return;
                }

                $poLine->loadMissing('invoiceLines');

                $alreadyInvoiced = (int) $poLine->invoiceLines->sum('quantity');
                $remaining = max(0, (int) $poLine->quantity - $alreadyInvoiced);
                $requested = (int) $group->sum('quantity');

                if ($requested > $remaining) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            sprintf(
                                'Line %d exceeds remaining quantity. Available: %d, requested: %d.',
                                $poLine->line_no ?? $poLineId,
                                $remaining,
                                $requested,
                            ),
                        ],
                    ]);
                }
            });

        return $resolved;
    }

    private function formatMinor(int $amountMinor, string $currency, int $minorUnit): string
    {
        return Money::fromMinor($amountMinor, strtoupper($currency))->toDecimal($minorUnit);
    }
}
