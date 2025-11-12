<?php

namespace App\Services;

use App\Enums\ExportRequestStatus;
use App\Enums\ExportRequestType;
use App\Jobs\ProcessExportRequestJob;
use App\Models\Company;
use App\Models\ExportRequest;
use App\Models\Plan;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

class ExportService
{
    private const DOWNLOAD_TTL_DAYS = 7;

    /**
     * @var list<string>
     */
    private const SUPPORTED_TABLES = [
        'companies',
        'users',
        'rfqs',
        'rfq_items',
        'quotes',
        'quote_items',
        'purchase_orders',
        'purchase_order_lines',
        'goods_receipt_notes',
        'goods_receipt_lines',
        'invoices',
        'invoice_lines',
        'invoice_matches',
        'credit_notes',
        'audit_logs',
        'documents',
        'notifications',
        'inventories',
        'inventory_txns',
        'assets',
        'orders',
        'supplier_applications',
        'suppliers',
        'supplier_documents',
        'supplier_risk_scores',
        'rmas',
        'rma_documents',
        'purchase_requisitions',
        'purchase_requisition_lines',
        'saved_searches',
    ];

    /**
     * @return list<string>
     */
    public static function supportedTables(): array
    {
        return self::SUPPORTED_TABLES;
    }

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function createRequest(User $user, string $type, array $filters = []): ExportRequest
    {
        $typeEnum = ExportRequestType::from($type);

        $user->loadMissing('company.plan');
        $company = $user->company;

        if (! $company instanceof Company) {
            throw ValidationException::withMessages([
                'company' => ['User must belong to a company to request an export.'],
            ]);
        }

        $plan = $company->plan;

        if (! $plan instanceof Plan || ! $plan->exports_enabled) {
            throw ValidationException::withMessages([
                'plan' => ['Current plan does not allow data exports.'],
            ]);
        }

        if ($plan->data_export_enabled === false) {
            throw ValidationException::withMessages([
                'plan' => ['Data exports are disabled for the current plan.'],
            ]);
        }

        $activeRequests = ExportRequest::query()
            ->where('company_id', $company->id)
            ->where('requested_by', $user->id)
            ->whereIn('status', [ExportRequestStatus::Pending, ExportRequestStatus::Processing])
            ->count();

        if ($activeRequests > 0) {
            throw ValidationException::withMessages([
                'concurrency' => ['An export is already processing for this user. Wait for it to finish before requesting another.'],
            ]);
        }

        $normalizedFilters = $this->normalizeFilters($typeEnum, $filters, (int) $plan->export_history_days);

        $exportRequest = ExportRequest::create([
            'company_id' => $company->id,
            'requested_by' => $user->id,
            'type' => $typeEnum,
            'status' => ExportRequestStatus::Pending,
            'filters' => $normalizedFilters ?: null,
        ]);

        $this->auditLogger->created($exportRequest, [
            'type' => $typeEnum->value,
            'filters' => $normalizedFilters,
        ]);

        ProcessExportRequestJob::dispatch($exportRequest->id);

        return $exportRequest->fresh(['requester']);
    }

    public function processRequest(ExportRequest $request): void
    {
        $request->loadMissing('company', 'requester');

        if (! $request->company instanceof Company) {
            throw new ModelNotFoundException('Export request missing company context.');
        }

        DB::transaction(function () use ($request): void {
            $request->forceFill([
                'status' => ExportRequestStatus::Processing,
                'error_message' => null,
            ])->save();
        });

        try {
            $files = match ($request->type) {
                ExportRequestType::FullData => $this->collectFullDataset($request),
                ExportRequestType::AuditLogs => $this->collectAuditLogDataset($request),
                ExportRequestType::Custom => $this->collectCustomDataset($request),
            };

            if (empty($files)) {
                $files = [
                    'metadata.json' => [
                        'message' => 'Export completed with no records matching the filters.',
                        'generated_at' => now()->toIso8601String(),
                    ],
                ];
            } else {
                $files['metadata.json'] = $this->buildMetadata($request, $files);
            }

            $relativePath = $this->writeArchive($request, $files);

            DB::transaction(function () use ($request, $relativePath): void {
                $request->forceFill([
                    'status' => ExportRequestStatus::Completed,
                    'file_path' => $relativePath,
                    'completed_at' => now(),
                    'expires_at' => now()->addDays(self::DOWNLOAD_TTL_DAYS),
                ])->save();
            });
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($request, $exception): void {
                $request->forceFill([
                    'status' => ExportRequestStatus::Failed,
                    'error_message' => $exception->getMessage(),
                ])->save();
            });

            report($exception);

            throw $exception;
        }
    }

    public function generateSignedUrl(ExportRequest $request): ?string
    {
        if (! $request->isDownloadable()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'exports.download',
            now()->addMinutes(10),
            ['exportRequest' => $request->getKey()]
        );
    }

    public function purgeExpiredExports(): int
    {
        $disk = Storage::disk('exports');
        $removed = 0;

        ExportRequest::query()
            ->whereNotNull('file_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->chunkById(100, function ($exports) use ($disk, &$removed): void {
                foreach ($exports as $export) {
                    if ($export->file_path !== null && $disk->exists($export->file_path)) {
                        $disk->delete($export->file_path);
                    }

                    $export->forceFill([
                        'file_path' => null,
                    ])->save();

                    $removed++;
                }
            });

        return $removed;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(ExportRequest $request, array $files): array
    {
        $fileSummaries = collect($files)
            ->except('metadata.json')
            ->map(static function ($content, string $filename): array {
                $count = is_array($content) ? count($content) : 1;

                return [
                    'name' => $filename,
                    'records' => $count,
                ];
            })
            ->values()
            ->all();

        return [
            'export_id' => $request->id,
            'type' => $request->type instanceof ExportRequestType ? $request->type->value : $request->type,
            'status' => $request->status instanceof ExportRequestStatus ? $request->status->value : $request->status,
            'generated_at' => now()->toIso8601String(),
            'filters' => $request->filters,
            'files' => $fileSummaries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectFullDataset(ExportRequest $request): array
    {
        $companyId = (int) $request->company_id;
        $files = [];

        foreach ($this->fullDataTables() as $table) {
            $data = $this->collectTable($table, $companyId, null, null);
            if (! empty($data)) {
                $files[$table.'.json'] = $data;
            }
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectAuditLogDataset(ExportRequest $request): array
    {
        [$from, $to] = $this->resolveDateFilters($request->filters ?? []);

        return [
            'audit_logs.json' => $this->collectTable('audit_logs', (int) $request->company_id, $from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectCustomDataset(ExportRequest $request): array
    {
        $filters = $request->filters ?? [];
        $tables = array_values(array_unique(array_filter(Arr::get($filters, 'tables', []), static function ($table): bool {
            return in_array($table, self::SUPPORTED_TABLES, true);
        })));

        if (empty($tables)) {
            return [];
        }

        [$from, $to] = $this->resolveDateFilters($filters);

        $files = [];

        foreach ($tables as $table) {
            $data = $this->collectTable($table, (int) $request->company_id, $from, $to);
            if (! empty($data)) {
                $files[$table.'.json'] = $data;
            }
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFilters(ExportRequestType $type, array $filters, int $historyDays): array
    {
        $normalized = [];
        $historyWindowStart = now()->subDays(max($historyDays, 0));

        $from = Arr::get($filters, 'from');
        $to = Arr::get($filters, 'to');

        $fromDate = $from !== null ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to !== null ? Carbon::parse($to)->endOfDay() : null;

        if ($type === ExportRequestType::AuditLogs) {
            $fromDate ??= now()->subDays(max($historyDays, 0))->startOfDay();
            $toDate ??= now()->endOfDay();
        }

        if ($fromDate !== null && $fromDate->lessThan($historyWindowStart)) {
            throw ValidationException::withMessages([
                'filters.from' => [sprintf('Requested history exceeds plan limit of %d days.', $historyDays)],
            ]);
        }

        if ($fromDate !== null && $toDate !== null && $toDate->lessThan($fromDate)) {
            throw ValidationException::withMessages([
                'filters.to' => ['End date must be after the start date.'],
            ]);
        }

        if ($fromDate !== null) {
            $normalized['from'] = $fromDate->toIso8601String();
        }

        if ($toDate !== null) {
            $normalized['to'] = $toDate->toIso8601String();
        }

        if ($type === ExportRequestType::Custom) {
            $tables = array_values(array_unique(array_filter(Arr::get($filters, 'tables', []), static function ($table): bool {
                return in_array($table, self::SUPPORTED_TABLES, true);
            })));

            if (empty($tables)) {
                throw ValidationException::withMessages([
                    'filters.tables' => ['At least one supported table must be provided for custom exports.'],
                ]);
            }

            if (in_array('audit_logs', $tables, true) && $fromDate !== null && $fromDate->lessThan($historyWindowStart)) {
                throw ValidationException::withMessages([
                    'filters.from' => [sprintf('Requested history exceeds plan limit of %d days.', $historyDays)],
                ]);
            }

            $normalized['tables'] = $tables;
        }

        return $normalized;
    }

    private function writeArchive(ExportRequest $request, array $files): string
    {
        $disk = Storage::disk('exports');
        $directory = (string) $request->company_id;
        $filename = sprintf(
            '%s-%s-%s.zip',
            $request->type instanceof ExportRequestType ? $request->type->value : $request->type,
            $request->id,
            now()->format('YmdHis')
        );

        $relativePath = $directory.'/'.$filename;
        $fullPath = $disk->path($relativePath);

        $folder = dirname($fullPath);

        if (! is_dir($folder) && ! mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new RuntimeException('Unable to create export directory: '.$folder);
        }

        $zip = new ZipArchive();

        if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create export archive.');
        }

        foreach ($files as $filenameInArchive => $content) {
            $payload = is_string($content)
                ? $content
                : json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $zip->addFromString($filenameInArchive, $payload ?? '');
        }

        $zip->close();

        return $relativePath;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveDateFilters(array $filters): array
    {
        $from = Arr::get($filters, 'from');
        $to = Arr::get($filters, 'to');

        $fromDate = $from !== null ? Carbon::parse($from) : null;
        $toDate = $to !== null ? Carbon::parse($to) : null;

        return [
            $fromDate?->startOfDay(),
            $toDate?->endOfDay(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function fullDataTables(): array
    {
        return ['companies', ...array_values(array_filter(
            self::SUPPORTED_TABLES,
            static fn (string $table): bool => $table !== 'companies'
        ))];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectTable(string $table, int $companyId, ?Carbon $from, ?Carbon $to): array
    {
        $query = DB::table($table);

        if ($table === 'companies') {
            $query->where('id', $companyId);
        } elseif (Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        } else {
            return [];
        }

        if ($from !== null && Schema::hasColumn($table, 'created_at')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null && Schema::hasColumn($table, 'created_at')) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->map(static function ($row): array {
            return (array) $row;
        })->all();
    }
}
