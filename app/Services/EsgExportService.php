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

    public function export(User $user, Supplier $supplier, Carbon $periodStart, Carbon $periodEnd): Document
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
        ];

        $document = $this->storeExport($user, $company, $supplier, $exportPayload, $periodStart, $periodEnd, $records->count());

        return $document;
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

    private function storeExport(
        User $user,
        Company $company,
        Supplier $supplier,
        array $payload,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $recordCount
    ): Document {
        $tempPath = tempnam(sys_get_temp_dir(), 'esg-pack-');

        if ($tempPath === false) {
            throw new InvalidArgumentException('Unable to create temporary file for ESG export.');
        }

        $filename = sprintf('scope-3-pack-%s-%s.pdf', $periodStart->format('Ymd'), $periodEnd->format('Ymd'));

        file_put_contents($tempPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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
                    ],
                ]
            );
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
