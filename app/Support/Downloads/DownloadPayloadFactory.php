<?php

namespace App\Support\Downloads;

use App\Enums\DownloadDocumentType;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DownloadJob;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DownloadPayloadFactory
{
    public function build(DownloadJob $job): DocumentDownloadPayload
    {
        $company = Company::query()
            ->with(['profile', 'localeSetting', 'moneySetting'])
            ->findOrFail($job->company_id);

        $formatter = new DocumentFormatter($company->localeSetting, $company->moneySetting);

        return match ($job->document_type) {
            DownloadDocumentType::Rfq => $this->forRfq($job, $company, $formatter),
            DownloadDocumentType::Quote => $this->forQuote($job, $company, $formatter),
            DownloadDocumentType::PurchaseOrder => $this->forPurchaseOrder($job, $company, $formatter),
            DownloadDocumentType::Invoice => $this->forInvoice($job, $company, $formatter),
            DownloadDocumentType::GoodsReceipt => $this->forGrn($job, $company, $formatter),
            DownloadDocumentType::CreditNote => $this->forCredit($job, $company, $formatter),
        };
    }

    private function forRfq(DownloadJob $job, Company $company, DocumentFormatter $formatter): DocumentDownloadPayload
    {
        $rfq = RFQ::query()
            ->with(['items'])
            ->findOrFail($job->document_id);

        $this->assertScope($job, $rfq->company_id);

        $reference = $job->reference ?: ($rfq->number ?? sprintf('RFQ-%05d', $rfq->getKey()));
        $currency = $rfq->currency ?: $company->moneySetting?->pricing_currency ?: $company->moneySetting?->base_currency ?: 'USD';

        $lineRows = [];
        $csvRows = [];

        foreach ($rfq->items as $item) {
            $lineRows[] = [
                (string) ($item->line_no ?? $item->getKey()),
                $item->part_name ?? '—',
                $formatter->quantity($item->quantity ?? 0),
                $item->uom ?? '—',
                $formatter->money($item->target_price_minor),
                $item->material ?? '—',
            ];

            $csvRows[] = [
                $item->line_no ?? $item->getKey(),
                $item->part_name ?? '',
                $item->quantity ?? 0,
                $item->uom ?? '',
                $this->minorToDecimal($item->target_price_minor ?? null, $item->target_price),
                $currency,
                $item->material ?? '',
            ];
        }

        $document = [
            'title' => 'Request for Quote',
            'logo_url' => $company->profile?->logo_url,
            'company_name' => $company->name,
            'reference_label' => 'RFQ Number',
            'reference' => $reference,
            'status' => $this->label($rfq->status ?? 'draft'),
            'date_label' => 'Deadline',
            'date' => $formatter->date($rfq->deadline_at),
            'summary' => [
                [
                    'label' => 'Overview',
                    'rows' => [
                        ['label' => 'Type', 'value' => $this->label($rfq->type ?? 'manufacture')],
                        ['label' => 'Quantity', 'value' => $formatter->quantity($rfq->quantity ?? count($rfq->items))],
                        ['label' => 'Incoterm', 'value' => $rfq->incoterm ?? '—'],
                    ],
                ],
            ],
            'parties' => [
                [
                    'label' => 'Buyer',
                    'name' => $company->name,
                    'details' => [
                        ['label' => 'Address', 'value' => $formatter->formatAddress($company->profile?->bill_to ?? $company->address) ?? '—'],
                        ['label' => 'Contact', 'value' => $company->primary_contact_name ?? '—'],
                    ],
                ],
            ],
            'line_table' => [
                'headers' => ['Line', 'Part / Description', 'Quantity', 'UOM', 'Target Price', 'Material'],
                'rows' => $lineRows,
            ],
            'totals' => [],
            'footer_note' => $rfq->notes ?? null,
            'generated_at' => now()->format('c'),
        ];

        $csv = array_merge(
            $this->csvMetaRows([
                'Document' => 'Request for Quote',
                'Reference' => $reference,
                'Status' => $this->label($rfq->status ?? 'draft'),
                'Deadline' => $formatter->date($rfq->deadline_at),
            ]),
            [[]],
            [['Line', 'Part', 'Quantity', 'UOM', 'Target Price', 'Currency', 'Material']],
            $csvRows,
        );

        return new DocumentDownloadPayload($job, $document, $csv, $this->baseFilename($job, $reference));
    }

    private function forQuote(DownloadJob $job, Company $company, DocumentFormatter $formatter): DocumentDownloadPayload
    {
        $quote = Quote::query()
            ->with(['items.rfqItem', 'supplier', 'rfq'])
            ->findOrFail($job->document_id);

        $this->assertScope($job, $quote->company_id);

        $reference = $job->reference ?: sprintf('QUOTE-%05d', $quote->getKey());
        $currency = $quote->currency ?: $company->moneySetting?->pricing_currency ?: 'USD';

        $lineRows = [];
        $csvRows = [];

        foreach ($quote->items as $item) {
            $rfqItem = $item->rfqItem;
            $quantity = max(0, (float) ($rfqItem?->quantity ?? 0));
            $unitMinor = $item->unit_price_minor ?? (int) round(($item->unit_price ?? 0) * 100);
            $lineTotal = (int) round($unitMinor * $quantity);

            $lineRows[] = [
                (string) ($rfqItem?->line_no ?? $item->getKey()),
                $rfqItem?->part_name ?? '—',
                $formatter->quantity($quantity),
                $rfqItem?->uom ?? '—',
                $formatter->money($unitMinor),
                $formatter->money($lineTotal),
            ];

            $csvRows[] = [
                $rfqItem?->line_no ?? $item->getKey(),
                $rfqItem?->part_name ?? '',
                $quantity,
                $rfqItem?->uom ?? '',
                $this->minorToDecimal($unitMinor),
                $currency,
                $this->minorToDecimal($lineTotal),
            ];
        }

        $supplier = $quote->supplier;

        $document = [
            'title' => 'Quote',
            'logo_url' => $company->profile?->logo_url,
            'company_name' => $company->name,
            'reference_label' => 'Quote Reference',
            'reference' => $reference,
            'status' => $this->label($quote->status ?? 'draft'),
            'date_label' => 'Submitted',
            'date' => $formatter->date($quote->submitted_at),
            'summary' => [
                [
                    'label' => 'Commercial',
                    'rows' => [
                        ['label' => 'RFQ', 'value' => $quote->rfq?->number ?? '—'],
                        ['label' => 'Lead Time (days)', 'value' => $quote->lead_time_days ?? '—'],
                        ['label' => 'Min order qty', 'value' => $quote->min_order_qty ?? '—'],
                    ],
                ],
            ],
            'parties' => [
                [
                    'label' => 'Supplier',
                    'name' => $supplier?->name ?? '—',
                    'details' => [
                        ['label' => 'Contact', 'value' => $supplier?->primary_contact_name ?? '—'],
                        ['label' => 'Email', 'value' => $supplier?->primary_contact_email ?? '—'],
                    ],
                ],
            ],
            'line_table' => [
                'headers' => ['Line', 'Part', 'Quantity', 'UOM', 'Unit Price', 'Extended Total'],
                'rows' => $lineRows,
            ],
            'totals' => [
                ['label' => 'Subtotal', 'value' => $formatter->money($quote->subtotal_minor)],
                ['label' => 'Tax', 'value' => $formatter->money($quote->tax_amount_minor)],
                ['label' => 'Total', 'value' => $formatter->money($quote->total_minor)],
            ],
            'footer_note' => $quote->note ?? null,
            'generated_at' => now()->format('c'),
        ];

        $csv = array_merge(
            $this->csvMetaRows([
                'Document' => 'Quote',
                'Reference' => $reference,
                'Status' => $this->label($quote->status ?? 'draft'),
                'Submitted' => $formatter->date($quote->submitted_at),
            ]),
            [[]],
            [['Line', 'Part', 'Quantity', 'UOM', 'Unit Price', 'Currency', 'Extended Total']],
            $csvRows,
        );

        return new DocumentDownloadPayload($job, $document, $csv, $this->baseFilename($job, $reference));
    }

    private function forPurchaseOrder(DownloadJob $job, Company $company, DocumentFormatter $formatter): DocumentDownloadPayload
    {
        $po = PurchaseOrder::query()
            ->with(['lines.taxes', 'quote.supplier'])
            ->findOrFail($job->document_id);

        $this->assertScope($job, $po->company_id);

        $reference = $job->reference ?: ($po->po_number ?? sprintf('PO-%05d', $po->getKey()));
        $currency = $po->currency ?? $company->moneySetting?->pricing_currency ?? 'USD';

        $lineRows = [];
        $csvRows = [];

        foreach ($po->lines as $line) {
            $unitMinor = $line->unit_price_minor ?? (int) round(($line->unit_price ?? 0) * 100);
            $quantity = (float) ($line->quantity ?? 0);
            $lineTax = (int) $line->taxes->sum('amount_minor');
            $lineTotal = (int) round($unitMinor * $quantity) + $lineTax;

            $lineRows[] = [
                (string) ($line->line_no ?? $line->getKey()),
                $line->description ?? '—',
                $formatter->quantity($quantity),
                $line->uom ?? '—',
                $formatter->money($unitMinor),
                $formatter->money($lineTax),
                $formatter->money($lineTotal),
            ];

            $csvRows[] = [
                $line->line_no ?? $line->getKey(),
                $line->description ?? '',
                $quantity,
                $line->uom ?? '',
                $this->minorToDecimal($unitMinor),
                $currency,
                $this->minorToDecimal($lineTax),
                $this->minorToDecimal($lineTotal),
            ];
        }

        $supplier = $po->quote?->supplier;
        $document = [
            'title' => 'Purchase Order',
            'logo_url' => $company->profile?->logo_url,
            'company_name' => $company->name,
            'reference_label' => 'PO Number',
            'reference' => $reference,
            'status' => $this->label($po->status ?? 'draft'),
            'date_label' => 'Issued',
            'date' => $formatter->date($po->created_at),
            'summary' => [
                [
                    'label' => 'Commercial',
                    'rows' => [
                        ['label' => 'Incoterm', 'value' => $po->incoterm ?? '—'],
                        ['label' => 'Revision', 'value' => $po->revision_no ?? '—'],
                        ['label' => 'Currency', 'value' => $currency],
                    ],
                ],
            ],
            'parties' => [
                [
                    'label' => 'Buyer',
                    'name' => $company->name,
                    'details' => [
                        ['label' => 'Bill to', 'value' => $formatter->formatAddress($company->profile?->bill_to ?? $company->address) ?? '—'],
                        ['label' => 'Ship to', 'value' => $formatter->formatAddress($company->profile?->ship_from ?? $company->address) ?? '—'],
                    ],
                ],
                [
                    'label' => 'Supplier',
                    'name' => $supplier?->name ?? '—',
                    'details' => [
                        ['label' => 'Primary contact', 'value' => $supplier?->primary_contact_name ?? '—'],
                        ['label' => 'Email', 'value' => $supplier?->primary_contact_email ?? '—'],
                    ],
                ],
            ],
            'line_table' => [
                'headers' => ['Line', 'Description', 'Quantity', 'UOM', 'Unit Price', 'Tax', 'Line Total'],
                'rows' => $lineRows,
            ],
            'totals' => [
                ['label' => 'Subtotal', 'value' => $formatter->money($po->subtotal_minor ?? null)],
                ['label' => 'Tax', 'value' => $formatter->money($po->tax_amount_minor ?? null)],
                ['label' => 'Grand Total', 'value' => $formatter->money($po->total_minor ?? null)],
            ],
            'footer_note' => null,
            'generated_at' => now()->format('c'),
        ];

        $csv = array_merge(
            $this->csvMetaRows([
                'Document' => 'Purchase Order',
                'Reference' => $reference,
                'Status' => $this->label($po->status ?? 'draft'),
                'Issued' => $formatter->date($po->created_at),
            ]),
            [[]],
            [['Line', 'Description', 'Quantity', 'UOM', 'Unit Price', 'Currency', 'Tax', 'Line Total']],
            $csvRows,
        );

        return new DocumentDownloadPayload($job, $document, $csv, $this->baseFilename($job, $reference));
    }

    private function forInvoice(DownloadJob $job, Company $company, DocumentFormatter $formatter): DocumentDownloadPayload
    {
        $invoice = Invoice::query()
            ->with(['lines.taxes', 'supplier', 'purchaseOrder'])
            ->findOrFail($job->document_id);

        $this->assertScope($job, $invoice->company_id);

        $reference = $job->reference ?: ($invoice->invoice_number ?? sprintf('INV-%05d', $invoice->getKey()));
        $currency = $invoice->currency ?? $company->moneySetting?->pricing_currency ?? 'USD';

        $lineRows = [];
        $csvRows = [];

        foreach ($invoice->lines as $line) {
            $unitMinor = $line->unit_price_minor ?? (int) round(($line->unit_price ?? 0) * 100);
            $quantity = (float) ($line->quantity ?? 0);
            $lineTax = (int) $line->taxes->sum('amount_minor');
            $lineTotal = (int) round($unitMinor * $quantity) + $lineTax;

            $lineRows[] = [
                (string) $line->getKey(),
                $line->description ?? '—',
                $formatter->quantity($quantity),
                $line->uom ?? '—',
                $formatter->money($unitMinor),
                $formatter->money($lineTax),
                $formatter->money($lineTotal),
            ];

            $csvRows[] = [
                $line->getKey(),
                $line->description ?? '',
                $quantity,
                $line->uom ?? '',
                $this->minorToDecimal($unitMinor),
                $currency,
                $this->minorToDecimal($lineTax),
                $this->minorToDecimal($lineTotal),
            ];
        }

        $supplier = $invoice->supplier;

        $document = [
            'title' => 'Invoice',
            'logo_url' => $company->profile?->logo_url,
            'company_name' => $company->name,
            'reference_label' => 'Invoice Number',
            'reference' => $reference,
            'status' => $this->label($invoice->status ?? 'draft'),
            'date_label' => 'Invoice Date',
            'date' => $formatter->date($invoice->invoice_date),
            'summary' => [
                [
                    'label' => 'Totals',
                    'rows' => [
                        ['label' => 'Subtotal', 'value' => $formatter->money((int) round(($invoice->subtotal ?? 0) * 100))],
                        ['label' => 'Tax', 'value' => $formatter->money((int) round(($invoice->tax_amount ?? 0) * 100))],
                        ['label' => 'Total', 'value' => $formatter->money((int) round(($invoice->total ?? 0) * 100))],
                    ],
                ],
            ],
            'parties' => [
                [
                    'label' => 'Supplier',
                    'name' => $supplier?->name ?? '—',
                    'details' => [
                        ['label' => 'Contact', 'value' => $supplier?->primary_contact_name ?? '—'],
                        ['label' => 'Email', 'value' => $supplier?->primary_contact_email ?? '—'],
                    ],
                ],
                [
                    'label' => 'Purchase Order',
                    'name' => $invoice->purchaseOrder?->po_number ?? '—',
                    'details' => [
                        ['label' => 'PO Total', 'value' => $invoice->purchaseOrder ? $formatter->money($invoice->purchaseOrder->total_minor ?? null) : '—'],
                    ],
                ],
            ],
            'line_table' => [
                'headers' => ['Line', 'Description', 'Quantity', 'UOM', 'Unit Price', 'Tax', 'Line Total'],
                'rows' => $lineRows,
            ],
            'totals' => [
                ['label' => 'Subtotal', 'value' => $formatter->money((int) round(($invoice->subtotal ?? 0) * 100))],
                ['label' => 'Tax', 'value' => $formatter->money((int) round(($invoice->tax_amount ?? 0) * 100))],
                ['label' => 'Grand Total', 'value' => $formatter->money((int) round(($invoice->total ?? 0) * 100))],
            ],
            'footer_note' => null,
            'generated_at' => now()->format('c'),
        ];

        $csv = array_merge(
            $this->csvMetaRows([
                'Document' => 'Invoice',
                'Reference' => $reference,
                'Status' => $this->label($invoice->status ?? 'draft'),
                'Invoice Date' => $formatter->date($invoice->invoice_date),
            ]),
            [[]],
            [['Line', 'Description', 'Quantity', 'UOM', 'Unit Price', 'Currency', 'Tax', 'Line Total']],
            $csvRows,
        );

        return new DocumentDownloadPayload($job, $document, $csv, $this->baseFilename($job, $reference));
    }

    private function forGrn(DownloadJob $job, Company $company, DocumentFormatter $formatter): DocumentDownloadPayload
    {
        $grn = GoodsReceiptNote::query()
            ->with(['lines.purchaseOrderLine', 'purchaseOrder.quote.supplier', 'inspector'])
            ->findOrFail($job->document_id);

        $this->assertScope($job, $grn->company_id);

        $reference = $job->reference ?: ($grn->number ?? sprintf('GRN-%05d', $grn->getKey()));

        $lineRows = [];
        $csvRows = [];

        foreach ($grn->lines as $line) {
            $poLine = $line->purchaseOrderLine;
            $lineRows[] = [
                (string) ($poLine?->line_no ?? $line->getKey()),
                $poLine?->description ?? '—',
                $formatter->quantity($line->received_qty ?? 0),
                $formatter->quantity($line->accepted_qty ?? 0),
                $formatter->quantity($line->rejected_qty ?? 0),
                $line->defect_notes ?? '—',
            ];

            $csvRows[] = [
                $poLine?->line_no ?? $line->getKey(),
                $poLine?->description ?? '',
                $line->received_qty ?? 0,
                $line->accepted_qty ?? 0,
                $line->rejected_qty ?? 0,
                $line->defect_notes ?? '',
            ];
        }

        $supplier = $grn->purchaseOrder?->quote?->supplier;

        $document = [
            'title' => 'Goods Receipt Note',
            'logo_url' => $company->profile?->logo_url,
            'company_name' => $company->name,
            'reference_label' => 'GRN Number',
            'reference' => $reference,
            'status' => $this->label($grn->status ?? 'open'),
            'date_label' => 'Inspected',
            'date' => $formatter->date($grn->inspected_at),
            'summary' => [
                [
                    'label' => 'Inspection',
                    'rows' => [
                        ['label' => 'Reference', 'value' => $grn->reference ?? '—'],
                        ['label' => 'Inspector', 'value' => $grn->inspector?->name ?? '—'],
                    ],
                ],
            ],
            'parties' => [
                [
                    'label' => 'Supplier',
                    'name' => $supplier?->name ?? '—',
                    'details' => [
                        ['label' => 'PO', 'value' => $grn->purchaseOrder?->po_number ?? '—'],
                    ],
                ],
            ],
            'line_table' => [
                'headers' => ['Line', 'Description', 'Received Qty', 'Accepted Qty', 'Rejected Qty', 'Notes'],
                'rows' => $lineRows,
            ],
            'totals' => [],
            'footer_note' => $grn->notes ?? null,
            'generated_at' => now()->format('c'),
        ];

        $csv = array_merge(
            $this->csvMetaRows([
                'Document' => 'Goods Receipt Note',
                'Reference' => $reference,
                'Status' => $this->label($grn->status ?? 'open'),
                'Inspected' => $formatter->date($grn->inspected_at),
            ]),
            [[]],
            [['Line', 'Description', 'Qty Received', 'Qty Accepted', 'Qty Rejected', 'Notes']],
            $csvRows,
        );

        return new DocumentDownloadPayload($job, $document, $csv, $this->baseFilename($job, $reference));
    }

    private function forCredit(DownloadJob $job, Company $company, DocumentFormatter $formatter): DocumentDownloadPayload
    {
        $credit = CreditNote::query()
            ->with(['lines.invoiceLine', 'invoice', 'purchaseOrder'])
            ->findOrFail($job->document_id);

        $this->assertScope($job, $credit->company_id);

        $reference = $job->reference ?: ($credit->credit_number ?? sprintf('CR-%05d', $credit->getKey()));
        $currency = $credit->currency ?? $company->moneySetting?->pricing_currency ?? 'USD';

        $lineRows = [];
        $csvRows = [];

        foreach ($credit->lines as $line) {
            $invoiceLine = $line->invoiceLine;
            $unitMinor = $line->unit_price_minor ?? (int) round(($invoiceLine?->unit_price ?? 0) * 100);
            $quantity = (float) ($line->qty_to_credit ?? 0);
            $lineTotal = $line->line_total_minor ?? (int) round($unitMinor * $quantity);

            $lineRows[] = [
                (string) $line->getKey(),
                $invoiceLine?->description ?? '—',
                $formatter->quantity($quantity),
                $invoiceLine?->uom ?? '—',
                $formatter->money($unitMinor),
                $formatter->money($lineTotal),
            ];

            $csvRows[] = [
                $line->getKey(),
                $invoiceLine?->description ?? '',
                $quantity,
                $invoiceLine?->uom ?? '',
                $this->minorToDecimal($unitMinor),
                $currency,
                $this->minorToDecimal($lineTotal),
            ];
        }

        $document = [
            'title' => 'Credit Note',
            'logo_url' => $company->profile?->logo_url,
            'company_name' => $company->name,
            'reference_label' => 'Credit Number',
            'reference' => $reference,
            'status' => $this->label($credit->status?->value ?? 'draft'),
            'date_label' => 'Approved',
            'date' => $formatter->date($credit->approved_at),
            'summary' => [
                [
                    'label' => 'Context',
                    'rows' => [
                        ['label' => 'Invoice', 'value' => $credit->invoice?->invoice_number ?? '—'],
                        ['label' => 'PO', 'value' => $credit->purchaseOrder?->po_number ?? '—'],
                        ['label' => 'Reason', 'value' => $credit->reason ?? '—'],
                    ],
                ],
            ],
            'parties' => [],
            'line_table' => [
                'headers' => ['Line', 'Description', 'Qty Credited', 'UOM', 'Unit Price', 'Line Total'],
                'rows' => $lineRows,
            ],
            'totals' => [
                ['label' => 'Credit Amount', 'value' => $formatter->money($credit->amount_minor ?? null)],
            ],
            'footer_note' => $credit->review_comment ?? null,
            'generated_at' => now()->format('c'),
        ];

        $csv = array_merge(
            $this->csvMetaRows([
                'Document' => 'Credit Note',
                'Reference' => $reference,
                'Status' => $this->label($credit->status?->value ?? 'draft'),
                'Approved' => $formatter->date($credit->approved_at),
            ]),
            [[]],
            [['Line', 'Description', 'Qty Credited', 'UOM', 'Unit Price', 'Currency', 'Line Total']],
            $csvRows,
        );

        return new DocumentDownloadPayload($job, $document, $csv, $this->baseFilename($job, $reference));
    }

    /**
     * @param array<string, string> $meta
     */
    private function csvMetaRows(array $meta): array
    {
        $rows = [];

        foreach ($meta as $label => $value) {
            $rows[] = [$label, $value];
        }

        return $rows;
    }

    private function baseFilename(DownloadJob $job, string $reference): string
    {
        $slug = Str::slug($reference);
        if ($slug === '') {
            $slug = sprintf('%s-%d', $job->document_type->value, $job->id);
        }

        return sprintf('%s-%s', $job->document_type->value, $slug);
    }

    private function label(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Str::headline(str_replace('_', ' ', strtolower($value)));
    }

    private function minorToDecimal(?int $minor, ?float $fallback = null): string
    {
        if ($minor !== null) {
            return number_format($minor / 100, 2, '.', '');
        }

        if ($fallback !== null) {
            return number_format($fallback, 2, '.', '');
        }

        return '0.00';
    }

    private function assertScope(DownloadJob $job, ?int $companyId): void
    {
        if ($companyId === null || (int) $companyId !== (int) $job->company_id) {
            throw ValidationException::withMessages([
                'document' => ['Document is not accessible for this download request.'],
            ]);
        }
    }
}
