<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AiServiceUnavailableException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AiEvent;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiEventRecorder;
use App\Services\AnalyticsService;
use App\Support\ActivePersonaContext;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnalyticsController extends ApiController
{
    private const PERMISSION_FORECAST = 'view_forecast_report';
    private const PERMISSION_SUPPLIER_PERFORMANCE = 'view_supplier_performance';
    private const REPORT_TYPE_FORECAST = 'forecast';
    private const REPORT_TYPE_SUPPLIER_PERFORMANCE = 'supplier_performance';

    private const SUPPLIER_OPTIONS_PER_PAGE_DEFAULT = 25;
    private const SUPPLIER_OPTIONS_PER_PAGE_MAX = 100;

    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AiClient $aiClient,
        private readonly AiEventRecorder $recorder,
        private readonly PermissionRegistry $permissionRegistry,
    ) {
    }

    public function forecastReport(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $company = $this->loadCompany($companyId);
        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        if ($response = $this->ensureAnalyticsPlan($company)) {
            return $response;
        }

        if (! $this->permissionRegistry->userHasAny($user, [self::PERMISSION_FORECAST], $companyId)) {
            return $this->fail('Access denied.', 403, [
                'code' => 'forecast_permission_required',
            ]);
        }

        try {
            $filters = $this->validateForecastFilters($request);
        } catch (ValidationException $exception) {
            return $this->fail('Invalid filters supplied.', 422, $exception->errors());
        }

        $report = $this->analyticsService->generateForecastReport($companyId, $filters);
        $summary = $this->summarizeReport(self::REPORT_TYPE_FORECAST, $company, $user, $report);

        $this->recordReportEvent(
            feature: 'forecast_report',
            companyId: $companyId,
            userId: $user->id,
            filtersUsed: $report['filters_used'] ?? [],
            report: $report,
            summary: $summary,
        );

        return $this->ok([
            'report' => $report,
            'summary' => $summary,
        ], 'Forecast report generated.');
    }

    public function supplierPerformanceReport(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $company = $this->loadCompany($companyId);
        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        if ($response = $this->ensureAnalyticsPlan($company)) {
            return $response;
        }

        if (! $this->permissionRegistry->userHasAny($user, [self::PERMISSION_SUPPLIER_PERFORMANCE], $companyId)) {
            return $this->fail('Access denied.', 403, [
                'code' => 'supplier_performance_permission_required',
            ]);
        }

        try {
            $filters = $this->validateSupplierFilters($request);
        } catch (ValidationException $exception) {
            return $this->fail('Invalid filters supplied.', 422, $exception->errors());
        }

        $supplierId = $this->resolveSupplierId($request);
        if ($supplierId === null) {
            return $this->fail('supplier_id is required.', 422, [
                'supplier_id' => ['Specify the supplier to analyze.'],
            ]);
        }

        $supplier = Supplier::query()
            ->where('company_id', $companyId)
            ->find($supplierId);

        if (! $supplier instanceof Supplier) {
            return $this->fail('Supplier not found.', 404);
        }

        if (! $this->supplierMatchesPersona($supplierId)) {
            return $this->fail('Access denied for this supplier.', 403);
        }

        $report = $this->analyticsService->generateSupplierPerformanceReport($companyId, $supplierId, $filters);
        $summary = $this->summarizeReport(self::REPORT_TYPE_SUPPLIER_PERFORMANCE, $company, $user, $report);

        $this->recordReportEvent(
            feature: 'supplier_performance_report',
            companyId: $companyId,
            userId: $user->id,
            filtersUsed: $report['filters_used'] ?? [],
            report: $report,
            summary: $summary,
        );

        return $this->ok([
            'report' => $report,
            'summary' => $summary,
        ], 'Supplier performance report generated.');
    }

    public function supplierOptions(Request $request): JsonResponse
    {
        $context = $this->requireCompanyContext($request);

        if ($context instanceof JsonResponse) {
            return $context;
        }

        ['user' => $user, 'companyId' => $companyId] = $context;

        $company = $this->loadCompany($companyId);
        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        if ($response = $this->ensureAnalyticsPlan($company)) {
            return $response;
        }

        if (! $this->permissionRegistry->userHasAny($user, [self::PERMISSION_SUPPLIER_PERFORMANCE], $companyId)) {
            return $this->fail('Access denied.', 403, [
                'code' => 'supplier_performance_permission_required',
            ]);
        }

        $query = trim((string) $request->query('q', ''));
        $perPage = $this->normalizeOptionsPerPage($request->query('per_page'));
        $selectedId = $request->query('selected_id');

        $persona = ActivePersonaContext::get();
        $personaSupplierId = $persona?->supplierId();

        $builder = Supplier::query()
            ->where('company_id', $companyId)
            ->select(['id', 'name'])
            ->when($persona !== null && $persona->isSupplier() && $personaSupplierId !== null, function ($query) use ($personaSupplierId): void {
                $query->whereKey($personaSupplierId);
            })
            ->when($query !== '', function ($queryBuilder) use ($query): void {
                $queryBuilder->where('name', 'like', $query.'%');
            })
            ->orderBy('name')
            ->limit($perPage);

        $suppliers = $builder->get();

        if ($personaSupplierId === null && is_numeric($selectedId)) {
            $selectedSupplier = Supplier::query()
                ->where('company_id', $companyId)
                ->select(['id', 'name'])
                ->find((int) $selectedId);

            if ($selectedSupplier instanceof Supplier && ! $suppliers->contains('id', $selectedSupplier->id)) {
                $suppliers->prepend($selectedSupplier);
            }
        }

        return $this->ok([
            'items' => $suppliers->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
            ]),
        ]);
    }

    private function loadCompany(int $companyId): ?Company
    {
        return Company::query()->with('plan')->find($companyId);
    }

    private function ensureAnalyticsPlan(Company $company): ?JsonResponse
    {
        $plan = $company->plan;

        if ($plan === null || ! $plan->analytics_enabled) {
            return $this->fail('Analytics not available on current plan.', 403, [
                'code' => 'analytics_upgrade_required',
            ]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForecastFilters(Request $request): array
    {
        $input = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'part_ids' => $this->normalizeQueryArray($request->query('part_ids')),
            'category_ids' => $this->normalizeQueryArray($request->query('category_ids')), 
            'location_ids' => $this->normalizeQueryArray($request->query('location_ids')),
        ];

        $validator = Validator::make($input, [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'part_ids' => ['array'],
            'part_ids.*' => ['integer', 'min:1'],
            'category_ids' => ['array'],
            'category_ids.*' => ['string'],
            'location_ids' => ['array'],
            'location_ids.*' => ['integer', 'min:1'],
        ]);

        $validated = $validator->validate();

        return [
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'part_ids' => $this->sanitizeIntegerList($validated['part_ids'] ?? []),
            'category_ids' => $this->sanitizeStringList($validated['category_ids'] ?? []),
            'location_ids' => $this->sanitizeIntegerList($validated['location_ids'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSupplierFilters(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        return $validator->validate();
    }

    private function normalizeQueryArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return array_filter(array_map('trim', explode(',', $value)), static fn ($entry) => $entry !== '');
        }

        if ($value === null) {
            return [];
        }

        return (array) $value;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int>
     */
    private function sanitizeIntegerList(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function sanitizeStringList(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();
    }

    private function resolveSupplierId(Request $request): ?int
    {
        $persona = ActivePersonaContext::get();
        $personaSupplierId = $persona?->supplierId();

        if ($persona !== null && $persona->isSupplier() && $personaSupplierId !== null) {
            return $personaSupplierId;
        }

        $supplierId = $request->query('supplier_id');

        if ($supplierId === null) {
            return null;
        }

        return is_numeric($supplierId) ? (int) $supplierId : null;
    }

    private function supplierMatchesPersona(int $supplierId): bool
    {
        $persona = ActivePersonaContext::get();

        if ($persona === null || ! $persona->isSupplier()) {
            return true;
        }

        $personaSupplierId = $persona->supplierId();

        if ($personaSupplierId === null) {
            return false;
        }

        return $personaSupplierId === $supplierId;
    }

    private function normalizeOptionsPerPage(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::SUPPLIER_OPTIONS_PER_PAGE_DEFAULT;
        }

        $perPage = (int) $value;

        if ($perPage < 1) {
            return 1;
        }

        if ($perPage > self::SUPPLIER_OPTIONS_PER_PAGE_MAX) {
            return self::SUPPLIER_OPTIONS_PER_PAGE_MAX;
        }

        return $perPage;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function summarizeReport(string $reportType, Company $company, User $user, array $report): array
    {
        $payload = [
            'report_type' => $reportType,
            'report_data' => [
                'series' => $report['series'] ?? [],
                'table' => $report['table'] ?? [],
                'aggregates' => $report['aggregates'] ?? [],
            ],
            'filters_used' => $report['filters_used'] ?? [],
            'company_id' => $company->id,
            'user_id_hash' => $this->hashUser($user),
        ];

        $startedAt = microtime(true);

        if (! $this->canSummarize($user, $company->id)) {
            return $this->recordSummaryFallback(
                reportType: $reportType,
                companyId: $company->id,
                userId: $user->id,
                payload: $payload,
                errorMessage: 'Summaries require the summarize_reports permission.',
                startedAt: $startedAt,
                latencyOverride: null,
                eventStatus: AiEvent::STATUS_SUCCESS
            );
        }

        try {
            $response = $this->aiClient->summarizeReport($payload);
        } catch (AiServiceUnavailableException $exception) {
            return $this->recordSummaryFallback($reportType, $company->id, $user->id, $payload, $exception->getMessage(), $startedAt);
        } catch (Throwable $exception) {
            report($exception);

            return $this->recordSummaryFallback($reportType, $company->id, $user->id, $payload, $exception->getMessage(), $startedAt);
        }

        $latency = $this->calculateLatencyMs($startedAt);

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            return $this->recordSummaryFallback(
                $reportType,
                $company->id,
                $user->id,
                $payload,
                $response['message'] ?? 'Summary generation failed.',
                $startedAt,
                $latency
            );
        }

        $summary = $this->normalizeSummaryPayload($response['data'], 'ai');

        $this->recorder->record(
            companyId: $company->id,
            userId: $user->id,
            feature: 'report_summary',
            requestPayload: [
                'report_type' => $reportType,
                'filters' => $payload['filters_used'],
            ],
            responsePayload: [
                'provider' => $summary['provider'],
                'source' => $summary['source'],
                'summary_preview' => $this->summaryPreview($summary['summary_markdown']),
            ],
            latencyMs: $latency,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: null,
            entityId: null,
        );

        return $summary;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeSummaryPayload(array $payload, string $source): array
    {
        $summaryMarkdown = is_string($payload['summary_markdown'] ?? null)
            ? trim($payload['summary_markdown'])
            : '';

        $bullets = array_values(array_filter(array_map(static function ($value) {
            if (is_string($value) && $value !== '') {
                return trim($value);
            }

            return null;
        }, $payload['bullets'] ?? [])));

        return [
            'summary_markdown' => $summaryMarkdown,
            'bullets' => $bullets,
            'source' => $payload['source'] ?? $source,
            'provider' => $payload['provider'] ?? 'ai',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function recordSummaryFallback(
        string $reportType,
        int $companyId,
        int $userId,
        array $payload,
        ?string $errorMessage,
        float $startedAt,
        ?int $latencyOverride = null,
        string $eventStatus = AiEvent::STATUS_ERROR
    ): array {
        $latency = $latencyOverride ?? $this->calculateLatencyMs($startedAt);

        $summary = $reportType === self::REPORT_TYPE_FORECAST
            ? $this->fallbackForecastSummary($payload)
            : $this->fallbackSupplierSummary($payload);

        $this->recorder->record(
            companyId: $companyId,
            userId: $userId,
            feature: 'report_summary',
            requestPayload: [
                'report_type' => $reportType,
                'filters' => $payload['filters_used'] ?? [],
            ],
            responsePayload: [
                'provider' => $summary['provider'],
                'source' => $summary['source'],
                'summary_preview' => $this->summaryPreview($summary['summary_markdown']),
                'error' => $errorMessage,
            ],
            latencyMs: $latency,
            status: $eventStatus,
            errorMessage: $errorMessage,
            entityType: null,
            entityId: null,
        );

        return $summary;
    }

    private function canSummarize(User $user, int $companyId): bool
    {
        if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
            return true;
        }

        return $this->permissionRegistry->userHasAny($user, ['summarize_reports'], $companyId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function fallbackForecastSummary(array $payload): array
    {
        $report = $payload['report_data']['aggregates'] ?? [];
        $filters = $payload['filters_used'] ?? [];

        $totalForecast = (float) ($report['total_forecast'] ?? 0.0);
        $totalActual = (float) ($report['total_actual'] ?? 0.0);
        $difference = $totalActual - $totalForecast;
        $range = $this->formatRangeLabel($filters);

        $summaryMarkdown = sprintf(
            'Between %s we recorded **%s** units of actual consumption versus **%s** forecasted units (%s variance).',
            $range,
            $this->formatNumber($totalActual),
            $this->formatNumber($totalForecast),
            $this->formatNumber($difference)
        );

        $bullets = [
            sprintf('MAPE %s and MAE %s units.', $this->formatPercent($report['mape'] ?? 0), $this->formatNumber($report['mae'] ?? 0, 2)),
            sprintf('Average daily demand is ~%s units.', $this->formatNumber($report['avg_daily_demand'] ?? 0, 2)),
            sprintf('Recommended reorder point %s with safety stock %s.',
                $this->formatNumber($report['recommended_reorder_point'] ?? 0, 2),
                $this->formatNumber($report['recommended_safety_stock'] ?? 0, 2)
            ),
        ];

        return [
            'summary_markdown' => $summaryMarkdown,
            'bullets' => $bullets,
            'source' => 'fallback',
            'provider' => 'deterministic',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function fallbackSupplierSummary(array $payload): array
    {
        $report = $payload['report_data']['aggregates'] ?? [];
        $table = $payload['report_data']['table'][0] ?? [];
        $filters = $payload['filters_used'] ?? [];

        $onTimeRateValue = $report['on_time_delivery_rate'] ?? $table['on_time_delivery_rate'] ?? 0;
        $defectRateValue = $report['defect_rate'] ?? $table['defect_rate'] ?? 0;
        $leadVarianceValue = $report['lead_time_variance'] ?? $table['lead_time_variance'] ?? 0;
        $priceVolatilityValue = $report['price_volatility'] ?? $table['price_volatility'] ?? 0;
        $responsivenessValue = $report['service_responsiveness'] ?? $table['service_responsiveness'] ?? 0;

        $onTimeRate = $this->formatPercent((float) $onTimeRateValue);
        $defectRate = $this->formatPercent((float) $defectRateValue);
        $leadVariance = $this->formatNumber((float) $leadVarianceValue, 2);
        $priceVolatility = $this->formatNumber((float) $priceVolatilityValue, 3);
        $responsiveness = $this->formatNumber((float) $responsivenessValue, 2);
        $range = $this->formatRangeLabel($filters);
        $riskCategory = Str::title((string) ($table['risk_category'] ?? 'unknown'));

        $summaryMarkdown = sprintf(
            'Performance from %s shows an on-time delivery rate of **%s** with **%s** defects.',
            $range,
            $onTimeRate,
            $defectRate
        );

        $bullets = [
            sprintf('Lead time volatility ~%s days; responsiveness around %s hours.', $leadVariance, $responsiveness),
            sprintf('Price volatility index %s; current risk category %s.', $priceVolatility, $riskCategory ?: 'Unknown'),
            'Monitor defect spikes alongside lead-time swings to protect service levels.',
        ];

        return [
            'summary_markdown' => $summaryMarkdown,
            'bullets' => $bullets,
            'source' => 'fallback',
            'provider' => 'deterministic',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function formatRangeLabel(array $filters): string
    {
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        if ($start && $end) {
            return sprintf('%s to %s', $start, $end);
        }

        if ($start) {
            return sprintf('%s onward', $start);
        }

        if ($end) {
            return sprintf('up to %s', $end);
        }

        return 'the selected period';
    }

    private function formatPercent(float $value): string
    {
        return number_format($value * 100, 1).' %';
    }

    private function formatNumber(float $value, int $decimals = 1): string
    {
        return number_format($value, $decimals);
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $summary
     */
    private function recordReportEvent(string $feature, int $companyId, int $userId, array $filtersUsed, array $report, array $summary): void
    {
        $seriesCount = is_countable($report['series'] ?? null) ? count($report['series']) : 0;
        $tableCount = is_countable($report['table'] ?? null) ? count($report['table']) : 0;

        $this->recorder->record(
            companyId: $companyId,
            userId: $userId,
            feature: $feature,
            requestPayload: [
                'filters' => $filtersUsed,
            ],
            responsePayload: [
                'summary_length' => mb_strlen($summary['summary_markdown'] ?? ''),
                'summary_source' => $summary['source'] ?? null,
                'series_count' => $seriesCount,
                'table_count' => $tableCount,
            ],
            latencyMs: null,
            status: AiEvent::STATUS_SUCCESS,
            errorMessage: null,
            entityType: null,
            entityId: null,
        );
    }

    private function calculateLatencyMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function summaryPreview(string $summary): string
    {
        return Str::limit(trim($summary), 280);
    }

    private function hashUser(User $user): string
    {
        $identifier = $user->email ?? (string) $user->getAuthIdentifier();
        $appKey = (string) config('app.key');

        return hash('sha256', sprintf('%s|%s', $appKey, $identifier));
    }
}
