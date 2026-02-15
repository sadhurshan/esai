<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\EsgCategory;
use App\Models\Company;
use App\Models\Document;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierEsgRecord;
use App\Models\User;
use App\Support\Documents\DocumentStorer;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EsgExportService
{
    public function __construct(private readonly DocumentStorer $documentStorer)
    {
    }

    /**
     * @return array{pdf: Document, csv: Document}
     */
    public function export(User $user, Supplier $supplier, Carbon $periodStart, Carbon $periodEnd): array
    {
        $supplier->loadMissing('company');
        $company = $supplier->company;

        if (! $company instanceof Company) {
            throw new InvalidArgumentException('Supplier must belong to a company for ESG export.');
        }

        $records = SupplierEsgRecord::query()
            ->with('document')
            ->where('supplier_id', $supplier->id)
            ->whereBetween('created_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
            ->get();

        $procurementLines = $this->procurementLines($supplier, $company, $periodStart, $periodEnd);
        $dataSources = $this->buildDataSources($records, $procurementLines);
        $assumptions = $this->buildAssumptions();

        $exportPayload = [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $periodStart->toDateString(),
                'to' => $periodEnd->toDateString(),
            ],
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_id' => $company->id,
            ],
            'esg_records' => $records->map(static function (SupplierEsgRecord $record): array {
                return [
                    'id' => $record->id,
                    'category' => $record->category?->value,
                    'name' => $record->name,
                    'description' => $record->description,
                    'document_id' => $record->document_id,
                    'expires_at' => $record->expires_at?->toIso8601String(),
                    'approved_at' => $record->approved_at?->toIso8601String(),
                    'meta' => $record->data_json ?? [],
                ];
            })->values()->all(),
            'emission_summary' => $this->summarizeEmissionData($records),
            'procurement_activity' => [
                'purchase_orders' => $procurementLines->pluck('po_number')->unique()->values()->all(),
                'lines' => $procurementLines->values()->all(),
            ],
            'data_sources' => $dataSources,
            'assumptions' => $assumptions,
        ];

        $recordCount = $records->count();
        $pdfDocument = $this->storePdfExport(
            $user,
            $company,
            $supplier,
            $exportPayload,
            $periodStart,
            $periodEnd,
            $recordCount,
            $dataSources,
            $assumptions,
        );
        $csvDocument = $this->storeCsvExport(
            $user,
            $company,
            $supplier,
            $exportPayload,
            $periodStart,
            $periodEnd,
            $recordCount,
            $dataSources,
            $assumptions,
        );

        return [
            'pdf' => $pdfDocument,
            'csv' => $csvDocument,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function procurementLines(Supplier $supplier, Company $company, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return PurchaseOrderLine::query()
            ->whereHas('purchaseOrder', function ($query) use ($supplier, $company, $periodStart, $periodEnd): void {
                $query->where('company_id', $company->id)
                    ->whereBetween('created_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
                    ->whereHas('quote', fn ($subQuery) => $subQuery->where('supplier_id', $supplier->id));
            })
            ->with(['purchaseOrder', 'rfqItem'])
            ->get()
            ->map(static function (PurchaseOrderLine $line): array {
                $purchaseOrder = $line->purchaseOrder;

                return [
                    'po_number' => $purchaseOrder?->po_number,
                    'line_no' => $line->line_no,
                    'description' => $line->description,
                    'quantity' => (int) $line->quantity,
                    'unit_price' => $line->unit_price !== null ? (float) $line->unit_price : null,
                    'rfq_item_id' => $line->rfq_item_id,
                    'rfq_reference' => $line->rfqItem?->description,
                ];
            });
    }

    private function summarizeEmissionData(Collection $records): array
    {
        $emissionData = $records
            ->filter(fn (SupplierEsgRecord $record) => $record->category === EsgCategory::Emission)
            ->flatMap(fn (SupplierEsgRecord $record) => $record->data_json ?? [])
            ->all();

        if (empty($emissionData)) {
            return [];
        }

        $numericValues = collect($emissionData)
            ->filter(static fn ($value): bool => is_numeric($value))
            ->map(static fn ($value): float => (float) $value);

        return [
            'metrics' => $emissionData,
            'totals' => [
                'count' => count($emissionData),
                'sum' => $numericValues->sum(),
                'average' => $numericValues->isEmpty() ? 0.0 : round($numericValues->avg(), 4),
            ],
        ];
    }

    private function storePdfExport(
        User $user,
        Company $company,
        Supplier $supplier,
        array $payload,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $recordCount,
        array $dataSources,
        array $assumptions,
    ): Document {
        $tempPath = tempnam(sys_get_temp_dir(), 'esg-pack-');

        if ($tempPath === false) {
            throw new InvalidArgumentException('Unable to create temporary file for ESG export.');
        }

        $filename = sprintf('scope-3-pack-%s-%s.pdf', $periodStart->format('Ymd'), $periodEnd->format('Ymd'));

        file_put_contents($tempPath, $this->buildPdfContent($payload));

        $uploaded = new UploadedFile($tempPath, $filename, 'application/pdf', null, true);

        try {
            return $this->documentStorer->store(
                $user,
                $uploaded,
                DocumentCategory::Esg->value,
                $company->id,
                $supplier->getMorphClass(),
                (int) $supplier->getKey(),
                [
                    'kind' => DocumentKind::EsgPack->value,
                    'visibility' => 'company',
                    'meta' => [
                        'period_start' => $periodStart->toDateString(),
                        'period_end' => $periodEnd->toDateString(),
                        'records_included' => $recordCount,
                        'format' => 'pdf',
                        'data_sources' => $dataSources,
                        'assumptions' => $assumptions,
                    ],
                ]
            );
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function storeCsvExport(
        User $user,
        Company $company,
        Supplier $supplier,
        array $payload,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $recordCount,
        array $dataSources,
        array $assumptions,
    ): Document {
        $tempPath = tempnam(sys_get_temp_dir(), 'esg-pack-');

        if ($tempPath === false) {
            throw new InvalidArgumentException('Unable to create temporary file for ESG export.');
        }

        $filename = sprintf('scope-3-pack-%s-%s.csv', $periodStart->format('Ymd'), $periodEnd->format('Ymd'));

        $handle = fopen($tempPath, 'w');

        if ($handle === false) {
            throw new InvalidArgumentException('Unable to write ESG export CSV.');
        }

        try {
            fputcsv($handle, ['section', 'key', 'value']);
            fputcsv($handle, ['summary', 'generated_at', $payload['generated_at'] ?? null]);
            fputcsv($handle, ['summary', 'period_from', $payload['period']['from'] ?? null]);
            fputcsv($handle, ['summary', 'period_to', $payload['period']['to'] ?? null]);
            fputcsv($handle, ['summary', 'supplier_id', $payload['supplier']['id'] ?? null]);
            fputcsv($handle, ['summary', 'supplier_name', $payload['supplier']['name'] ?? null]);
            fputcsv($handle, ['summary', 'record_count', $recordCount]);
            fputcsv($handle, ['summary', 'purchase_order_count', count($payload['procurement_activity']['purchase_orders'] ?? [])]);
            fputcsv($handle, ['summary', 'procurement_line_count', count($payload['procurement_activity']['lines'] ?? [])]);

            fputcsv($handle, []);
            fputcsv($handle, ['data_sources', 'source', 'details']);
            foreach ($dataSources as $source) {
                fputcsv($handle, ['data_sources', $source, null]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['assumptions', 'assumption', null]);
            foreach ($assumptions as $assumption) {
                fputcsv($handle, ['assumptions', $assumption, null]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['esg_records', 'id', 'category', 'name', 'description', 'document_id', 'expires_at', 'approved_at']);
            foreach ($payload['esg_records'] ?? [] as $record) {
                fputcsv($handle, [
                    'esg_records',
                    $record['id'] ?? null,
                    $record['category'] ?? null,
                    $record['name'] ?? null,
                    $record['description'] ?? null,
                    $record['document_id'] ?? null,
                    $record['expires_at'] ?? null,
                    $record['approved_at'] ?? null,
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['procurement_lines', 'po_number', 'line_no', 'description', 'quantity', 'unit_price', 'rfq_item_id', 'rfq_reference']);
            foreach ($payload['procurement_activity']['lines'] ?? [] as $line) {
                fputcsv($handle, [
                    'procurement_lines',
                    $line['po_number'] ?? null,
                    $line['line_no'] ?? null,
                    $line['description'] ?? null,
                    $line['quantity'] ?? null,
                    $line['unit_price'] ?? null,
                    $line['rfq_item_id'] ?? null,
                    $line['rfq_reference'] ?? null,
                ]);
            }
        } finally {
            fclose($handle);
        }

        $uploaded = new UploadedFile($tempPath, $filename, 'text/csv', null, true);

        try {
            return $this->documentStorer->store(
                $user,
                $uploaded,
                DocumentCategory::Esg->value,
                $company->id,
                $supplier->getMorphClass(),
                (int) $supplier->getKey(),
                [
                    'kind' => DocumentKind::EsgPack->value,
                    'visibility' => 'company',
                    'meta' => [
                        'period_start' => $periodStart->toDateString(),
                        'period_end' => $periodEnd->toDateString(),
                        'records_included' => $recordCount,
                        'format' => 'csv',
                        'data_sources' => $dataSources,
                        'assumptions' => $assumptions,
                    ],
                ]
            );
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function buildDataSources(Collection $records, Collection $procurementLines): array
    {
        $sources = ['supplier_esg_records', 'purchase_orders', 'purchase_order_lines'];

        if ($records->contains(fn (SupplierEsgRecord $record) => $record->document_id !== null)) {
            $sources[] = 'supplier_documents';
        }

        if ($procurementLines->contains(fn (array $line) => ! empty($line['rfq_item_id']))) {
            $sources[] = 'rfq_items';
        }

        return array_values(array_unique($sources));
    }

    private function buildAssumptions(): array
    {
        return [
            'Emission totals summarize numeric values captured in emission ESG records.',
            'Procurement lines include PO lines tied to supplier quotes during the export window.',
            'Scope-3 pack reflects tenant-approved ESG records and attachments as of the export date.',
        ];
    }

    private function buildPdfContent(array $payload): string
    {
        $lines = [
            'Scope-3 Support Pack',
            'Generated at: '.($payload['generated_at'] ?? ''),
            'Period: '.($payload['period']['from'] ?? '').' to '.($payload['period']['to'] ?? ''),
            'Supplier: '.($payload['supplier']['name'] ?? '').' (#'.($payload['supplier']['id'] ?? '').')',
            'ESG records: '.count($payload['esg_records'] ?? []),
            'Purchase orders: '.count($payload['procurement_activity']['purchase_orders'] ?? []),
            'Procurement lines: '.count($payload['procurement_activity']['lines'] ?? []),
        ];

        $dataSources = $payload['data_sources'] ?? [];
        if (is_array($dataSources) && $dataSources !== []) {
            $lines[] = 'Data sources: '.implode(', ', $dataSources);
        }

        $assumptions = $payload['assumptions'] ?? [];
        if (is_array($assumptions) && $assumptions !== []) {
            $lines[] = 'Assumptions:';
            foreach ($assumptions as $assumption) {
                $lines[] = '- '.$assumption;
            }
        }

        $emissionSummary = $payload['emission_summary']['totals'] ?? null;
        if (is_array($emissionSummary)) {
            $lines[] = sprintf(
                'Emission totals: count=%s, sum=%s, average=%s',
                $emissionSummary['count'] ?? 0,
                $emissionSummary['sum'] ?? 0,
                $emissionSummary['average'] ?? 0,
            );
        }

        return $this->renderSimplePdf($lines);
    }

    private function renderSimplePdf(array $lines): string
    {
        $escaped = array_map([$this, 'escapePdfText'], $lines);
        $textLines = "BT\n/F1 12 Tf\n72 720 Td\n";
        foreach ($escaped as $line) {
            $textLines .= sprintf('(%s) Tj\nT*\n', $line);
        }
        $textLines .= "ET\n";

        $contentLength = strlen($textLines);

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            4 => "<< /Length {$contentLength} >>\nstream\n{$textLines}\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[$index] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $index, $object);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
