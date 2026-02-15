import { useSdkClient } from '@/contexts/api-client-context';
import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import { AnalyticsApi, type ApiSuccessResponse } from '@/sdk';
import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

export interface AnalyticsKpis {
    openRfqs: number;
    avgCycleTimeDays: number;
    quotesReceived: number;
    spendTotal: number;
    onTimeReceiptsPct: number;
}

export interface RfqTrendPoint {
    periodStart: string | null;
    periodEnd: string | null;
    rfqs: number;
    quotes: number;
}

export interface SupplierSpendPoint {
    supplierId: number | null;
    supplierName: string;
    total: number;
}

export interface ReceiptPerformancePoint {
    periodStart: string | null;
    periodEnd: string | null;
    onTime: number;
    late: number;
}

export interface AnalyticsCharts {
    rfqsOverTime: RfqTrendPoint[];
    spendBySupplier: SupplierSpendPoint[];
    receiptsPerformance: ReceiptPerformancePoint[];
}

export interface AnalyticsOverviewResult {
    kpis: AnalyticsKpis;
    charts: AnalyticsCharts;
    meta: {
        from?: string;
        to?: string;
    };
}

export interface ReportSummary {
    summaryMarkdown: string;
    bullets: string[];
    source: string;
    provider: string;
}

export interface ForecastReportSeriesPoint {
    date: string;
    actual: number;
    forecast: number;
}

export interface ForecastReportSeries {
    partId: number;
    partName: string;
    data: ForecastReportSeriesPoint[];
}

export interface ForecastReportRow {
    partId: number;
    partName: string;
    totalForecast: number;
    totalActual: number;
    mape: number;
    mae: number;
    reorderPoint: number;
    safetyStock: number;
}

export interface ForecastReportAggregates {
    totalForecast: number;
    totalActual: number;
    mape: number;
    mae: number;
    avgDailyDemand: number;
    recommendedReorderPoint: number;
    recommendedSafetyStock: number;
}

export interface ForecastReportFiltersUsed {
    startDate: string | null;
    endDate: string | null;
    bucket: string;
    partIds: number[];
    categoryIds: string[];
    locationIds: number[];
}

export interface ForecastReport {
    series: ForecastReportSeries[];
    table: ForecastReportRow[];
    aggregates: ForecastReportAggregates;
    filtersUsed: ForecastReportFiltersUsed;
}

export interface ForecastReportResult {
    report: ForecastReport;
    summary: ReportSummary;
}

export interface SupplierMetricPoint {
    date: string;
    value: number;
}

export interface SupplierPerformanceMetricSeries {
    metricName: string;
    label: string;
    data: SupplierMetricPoint[];
}

export interface SupplierPerformanceTableRow {
    supplierId: number | null;
    supplierName: string | null;
    onTimeDeliveryRate: number;
    defectRate: number;
    leadTimeVariance: number;
    priceVolatility: number;
    serviceResponsiveness: number;
    riskScore: number | null;
    riskCategory: string | null;
}

export interface SupplierPerformanceFiltersUsed {
    startDate: string | null;
    endDate: string | null;
    bucket: string;
    supplierId: number | null;
}

export interface SupplierPerformanceReport {
    series: SupplierPerformanceMetricSeries[];
    table: SupplierPerformanceTableRow[];
    filtersUsed: SupplierPerformanceFiltersUsed;
}

export interface SupplierPerformanceReportResult {
    report: SupplierPerformanceReport;
    summary: ReportSummary;
}

export interface AnalyticsSupplierOption {
    id: number;
    name: string;
}

interface SupplierOptionApiPayload {
    id?: number | string | null;
    name?: string | null;
}

interface SupplierOptionResponse {
    items?: SupplierOptionApiPayload[];
}

export interface ForecastReportParams {
    startDate?: string | null;
    endDate?: string | null;
    partIds?: Array<number | string>;
    categoryIds?: string[];
    locationIds?: Array<number | string>;
}

export interface SupplierPerformanceParams {
    supplierId?: number | string | null;
    startDate?: string | null;
    endDate?: string | null;
}

interface ReportHookOptions {
    enabled?: boolean;
}

interface SnapshotPayload {
    type?: string | null;
    period_start?: string | null;
    period_end?: string | null;
    value?: number | string | null;
    meta?: Record<string, unknown> | null;
}

const PLACEHOLDER_OVERVIEW: AnalyticsOverviewResult = Object.freeze({
    kpis: {
        openRfqs: 0,
        avgCycleTimeDays: 0,
        quotesReceived: 0,
        spendTotal: 0,
        onTimeReceiptsPct: 0,
    },
    charts: {
        rfqsOverTime: [],
        spendBySupplier: [],
        receiptsPerformance: [],
    },
    meta: {},
});

const DEFAULT_REPORT_SUMMARY: ReportSummary = {
    summaryMarkdown: '',
    bullets: [],
    source: 'fallback',
    provider: 'deterministic',
};

const DEFAULT_FORECAST_AGGREGATES: ForecastReportAggregates = {
    totalForecast: 0,
    totalActual: 0,
    mape: 0,
    mae: 0,
    avgDailyDemand: 0,
    recommendedReorderPoint: 0,
    recommendedSafetyStock: 0,
};

const DEFAULT_FORECAST_FILTERS: ForecastReportFiltersUsed = {
    startDate: null,
    endDate: null,
    bucket: 'daily',
    partIds: [],
    categoryIds: [],
    locationIds: [],
};

const DEFAULT_SUPPLIER_FILTERS: SupplierPerformanceFiltersUsed = {
    startDate: null,
    endDate: null,
    bucket: 'weekly',
    supplierId: null,
};

export function useAnalyticsOverview(
    enabled: boolean,
): UseQueryResult<AnalyticsOverviewResult, unknown> {
    const analyticsApi = useSdkClient(AnalyticsApi);

    return useQuery<AnalyticsOverviewResult>({
        queryKey: queryKeys.analytics.overview(),
        enabled,
        queryFn: async () => {
            const response = await analyticsApi.analyticsOverview();

            return normalizeOverviewResponse(response);
        },
        placeholderData: PLACEHOLDER_OVERVIEW,
        staleTime: 60_000,
    });
}

export function useForecastReport(
    params: ForecastReportParams = {},
    options?: ReportHookOptions,
): UseQueryResult<ForecastReportResult, ApiError> {
    const queryParams = buildForecastReportQueryParams(params);

    return useQuery<ForecastReportResult, ApiError>({
        queryKey: queryKeys.analytics.forecastReport(queryParams),
        enabled: options?.enabled ?? true,
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const query = buildQuery(queryParams);
            const response = (await api.post(
                `/v1/analytics/forecast-report${query}`,
            )) as unknown;

            return normalizeForecastReportResponse(response);
        },
        staleTime: 60_000,
    });
}

export function useSupplierPerformanceReport(
    params: SupplierPerformanceParams = {},
    options?: ReportHookOptions,
): UseQueryResult<SupplierPerformanceReportResult, ApiError> {
    const queryParams = buildSupplierPerformanceQueryParams(params);

    return useQuery<SupplierPerformanceReportResult, ApiError>({
        queryKey: queryKeys.analytics.supplierPerformanceReport(queryParams),
        enabled: options?.enabled ?? true,
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const query = buildQuery(queryParams);
            const response = (await api.post(
                `/v1/analytics/supplier-performance-report${query}`,
            )) as unknown;

            return normalizeSupplierReportResponse(response);
        },
        staleTime: 60_000,
    });
}

interface SupplierOptionsParams {
    search?: string;
    selectedId?: string | number | null;
    perPage?: number;
    enabled?: boolean;
}

export function useAnalyticsSupplierOptions(
    params: SupplierOptionsParams = {},
): UseQueryResult<AnalyticsSupplierOption[], ApiError> {
    const queryParams = {
        q: params.search || undefined,
        selected_id: params.selectedId ?? undefined,
        per_page: params.perPage ?? undefined,
    } satisfies Record<string, unknown>;

    const enabled = params.enabled ?? true;

    return useQuery<AnalyticsSupplierOption[], ApiError>({
        queryKey: queryKeys.analytics.supplierOptions(queryParams),
        enabled,
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const query = buildQuery(queryParams);
            const response = (await api.get(
                `/v1/analytics/supplier-options${query}`,
            )) as SupplierOptionResponse | null;
            const rawItems = Array.isArray(response?.items)
                ? response?.items
                : [];

            return rawItems
                .map((item) => normalizeSupplierOption(item))
                .filter(
                    (item): item is AnalyticsSupplierOption => item !== null,
                );
        },
        staleTime: 60_000,
    });
}

function normalizeOverviewResponse(
    response: ApiSuccessResponse | null,
): AnalyticsOverviewResult {
    if (!response || typeof response !== 'object') {
        return PLACEHOLDER_OVERVIEW;
    }

    const snapshots = mapSnapshotGroups(response.data);
    const cycleSnapshots = snapshots['cycle_time'] ?? [];
    const responseSnapshots = snapshots['response_rate'] ?? [];
    const spendSnapshots = snapshots['spend'] ?? [];
    const otifSnapshots = snapshots['otif'] ?? [];

    const latestCycle = getLastSnapshot(cycleSnapshots);
    const latestResponse = getLastSnapshot(responseSnapshots);
    const latestSpend = getLastSnapshot(spendSnapshots);
    const latestOtif = getLastSnapshot(otifSnapshots);

    const kpis: AnalyticsKpis = {
        openRfqs: readMetaNumber(latestResponse, 'rfq_count'),
        avgCycleTimeDays: toNumber(latestCycle?.value),
        quotesReceived: readMetaNumber(latestResponse, 'quotes_submitted'),
        spendTotal: toNumber(latestSpend?.value),
        onTimeReceiptsPct: clampPercentage(toNumber(latestOtif?.value) * 100),
    };

    const charts: AnalyticsCharts = {
        rfqsOverTime: responseSnapshots.map((snapshot) => ({
            periodStart: snapshot.period_start ?? null,
            periodEnd: snapshot.period_end ?? null,
            rfqs: readMetaNumber(snapshot, 'rfq_count'),
            quotes: readMetaNumber(snapshot, 'quotes_submitted'),
        })),
        spendBySupplier: mapTopSuppliers(latestSpend),
        receiptsPerformance: otifSnapshots.map((snapshot) => {
            const totalLines = readMetaNumber(snapshot, 'total_lines');
            const onTime = readMetaNumber(snapshot, 'on_time_lines');
            const late = Math.max(0, totalLines - onTime);

            return {
                periodStart: snapshot.period_start ?? null,
                periodEnd: snapshot.period_end ?? null,
                onTime,
                late,
            } satisfies ReceiptPerformancePoint;
        }),
    };

    const metaPayload = (response.meta ?? {}) as Record<string, unknown>;
    const meta = {
        from:
            typeof metaPayload.from === 'string' ? metaPayload.from : undefined,
        to: typeof metaPayload.to === 'string' ? metaPayload.to : undefined,
    };

    return { kpis, charts, meta };
}

function mapSnapshotGroups(
    payload: unknown,
): Record<string, SnapshotPayload[]> {
    if (!payload || typeof payload !== 'object') {
        return {};
    }

    return Object.entries(payload as Record<string, unknown>).reduce<
        Record<string, SnapshotPayload[]>
    >((acc, [key, value]) => {
        if (Array.isArray(value)) {
            acc[key] = value
                .map((entry) => normalizeSnapshot(entry))
                .filter((entry): entry is SnapshotPayload => entry !== null);
        }

        return acc;
    }, {});
}

function normalizeSnapshot(entry: unknown): SnapshotPayload | null {
    if (!entry || typeof entry !== 'object') {
        return null;
    }

    const record = entry as Record<string, unknown>;

    return {
        type: typeof record.type === 'string' ? record.type : null,
        period_start:
            typeof record.period_start === 'string'
                ? record.period_start
                : null,
        period_end:
            typeof record.period_end === 'string' ? record.period_end : null,
        value: toNumber(record.value),
        meta:
            typeof record.meta === 'object' && record.meta !== null
                ? (record.meta as Record<string, unknown>)
                : undefined,
    };
}

function normalizeSupplierOption(
    payload: SupplierOptionApiPayload | null | undefined,
): AnalyticsSupplierOption | null {
    if (!payload) {
        return null;
    }

    const numericId =
        typeof payload.id === 'string' || typeof payload.id === 'number'
            ? Number(payload.id)
            : NaN;

    if (!Number.isFinite(numericId) || numericId <= 0) {
        return null;
    }

    const name =
        typeof payload.name === 'string' && payload.name.trim().length > 0
            ? payload.name.trim()
            : `Supplier #${numericId}`;

    return {
        id: numericId,
        name,
    };
}

function getLastSnapshot(list: SnapshotPayload[]): SnapshotPayload | null {
    if (list.length === 0) {
        return null;
    }

    return list[list.length - 1];
}

function readMetaNumber(snapshot: SnapshotPayload | null, key: string): number {
    if (!snapshot?.meta) {
        return 0;
    }

    const value = (snapshot.meta as Record<string, unknown>)[key];

    return toNumber(value);
}

function toNumber(value: unknown): number {
    if (value === null || value === undefined) {
        return 0;
    }

    const numeric = Number(value);

    return Number.isFinite(numeric) ? numeric : 0;
}

function clampPercentage(value: number): number {
    if (!Number.isFinite(value)) {
        return 0;
    }

    if (value < 0) {
        return 0;
    }

    if (value > 100) {
        return 100;
    }

    return Number(value.toFixed(2));
}

function mapTopSuppliers(
    snapshot: SnapshotPayload | null,
): SupplierSpendPoint[] {
    if (!snapshot?.meta) {
        return [];
    }

    const suppliers = (snapshot.meta as Record<string, unknown>)[
        'top_suppliers'
    ];

    if (!Array.isArray(suppliers)) {
        return [];
    }

    return suppliers.map((entry) => {
        const record = entry as Record<string, unknown>;
        const supplierIdRaw = record['supplier_id'];
        const supplierNameRaw = record['supplier_name'];
        const totalRaw = record['total'];

        return {
            supplierId: normalizeSupplierId(supplierIdRaw),
            supplierName:
                typeof supplierNameRaw === 'string' &&
                supplierNameRaw.trim().length > 0
                    ? supplierNameRaw
                    : 'Supplier',
            total: toNumber(totalRaw),
        } satisfies SupplierSpendPoint;
    });
}

function normalizeSupplierId(value: unknown): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function buildForecastReportQueryParams(
    params: ForecastReportParams = {},
): Record<string, unknown> {
    const query: Record<string, unknown> = {};
    const start = sanitizeDateInput(params.startDate);
    const end = sanitizeDateInput(params.endDate);
    const partIds = normalizeNumberArray(params.partIds);
    const categoryIds = normalizeStringArray(params.categoryIds);
    const locationIds = normalizeNumberArray(params.locationIds);

    if (start) {
        query.start_date = start;
    }

    if (end) {
        query.end_date = end;
    }

    if (partIds.length > 0) {
        query.part_ids = partIds;
    }

    if (categoryIds.length > 0) {
        query.category_ids = categoryIds;
    }

    if (locationIds.length > 0) {
        query.location_ids = locationIds;
    }

    return query;
}

function buildSupplierPerformanceQueryParams(
    params: SupplierPerformanceParams = {},
): Record<string, unknown> {
    const query: Record<string, unknown> = {};
    const start = sanitizeDateInput(params.startDate);
    const end = sanitizeDateInput(params.endDate);
    const supplierId = normalizeSupplierId(params.supplierId ?? null);

    if (start) {
        query.start_date = start;
    }

    if (end) {
        query.end_date = end;
    }

    if (supplierId !== null) {
        query.supplier_id = supplierId;
    }

    return query;
}

function normalizeForecastReportResponse(
    payload: unknown,
): ForecastReportResult {
    if (!payload || typeof payload !== 'object') {
        return createEmptyForecastReportResult();
    }

    const record = payload as Record<string, unknown>;

    return {
        report: normalizeForecastReport(record.report),
        summary: normalizeReportSummary(record.summary),
    };
}

function normalizeSupplierReportResponse(
    payload: unknown,
): SupplierPerformanceReportResult {
    if (!payload || typeof payload !== 'object') {
        return createEmptySupplierReportResult();
    }

    const record = payload as Record<string, unknown>;

    return {
        report: normalizeSupplierReport(record.report),
        summary: normalizeReportSummary(record.summary),
    };
}

function normalizeForecastReport(value: unknown): ForecastReport {
    if (!value || typeof value !== 'object') {
        return createEmptyForecastReportResult().report;
    }

    const record = value as Record<string, unknown>;

    return {
        series: normalizeForecastSeries(record.series),
        table: normalizeForecastTable(record.table),
        aggregates: normalizeForecastAggregates(record.aggregates),
        filtersUsed: normalizeForecastFilters(
            record.filters_used ?? record.filtersUsed,
        ),
    };
}

function normalizeSupplierReport(value: unknown): SupplierPerformanceReport {
    if (!value || typeof value !== 'object') {
        return createEmptySupplierReportResult().report;
    }

    const record = value as Record<string, unknown>;

    return {
        series: normalizeSupplierSeries(record.series),
        table: normalizeSupplierTable(record.table),
        filtersUsed: normalizeSupplierFilters(
            record.filters_used ?? record.filtersUsed,
        ),
    };
}

function normalizeForecastSeries(value: unknown): ForecastReportSeries[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => {
            if (!entry || typeof entry !== 'object') {
                return null;
            }

            const record = entry as Record<string, unknown>;
            const dataPoints = Array.isArray(record.data)
                ? record.data
                      .map((point) => normalizeForecastSeriesPoint(point))
                      .filter(
                          (point): point is ForecastReportSeriesPoint =>
                              point !== null,
                      )
                : [];

            const partId =
                normalizeSupplierId(record.part_id ?? record.partId) ?? 0;
            const partName =
                readStringField(record, 'part_name', 'partName') ??
                `Part ${partId > 0 ? partId : ''}`.trim();

            return {
                partId,
                partName: partName || 'Part',
                data: dataPoints,
            } satisfies ForecastReportSeries;
        })
        .filter((entry): entry is ForecastReportSeries => entry !== null);
}

function normalizeForecastSeriesPoint(
    entry: unknown,
): ForecastReportSeriesPoint | null {
    if (!entry || typeof entry !== 'object') {
        return null;
    }

    const record = entry as Record<string, unknown>;
    const date = readStringField(record, 'date');

    if (!date) {
        return null;
    }

    return {
        date,
        actual: toNumber(record.actual),
        forecast: toNumber(record.forecast),
    };
}

function normalizeForecastTable(value: unknown): ForecastReportRow[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => {
            if (!entry || typeof entry !== 'object') {
                return null;
            }

            const record = entry as Record<string, unknown>;
            const partId =
                normalizeSupplierId(record.part_id ?? record.partId) ?? 0;
            const partName =
                readStringField(record, 'part_name', 'partName') ??
                `Part ${partId > 0 ? partId : ''}`.trim();

            return {
                partId,
                partName: partName || 'Part',
                totalForecast: toNumber(
                    record.total_forecast ?? record.totalForecast,
                ),
                totalActual: toNumber(
                    record.total_actual ?? record.totalActual,
                ),
                mape: toNumber(record.mape),
                mae: toNumber(record.mae),
                reorderPoint: toNumber(
                    record.reorder_point ?? record.reorderPoint,
                ),
                safetyStock: toNumber(
                    record.safety_stock ?? record.safetyStock,
                ),
            } satisfies ForecastReportRow;
        })
        .filter((row): row is ForecastReportRow => row !== null);
}

function normalizeForecastAggregates(value: unknown): ForecastReportAggregates {
    if (!value || typeof value !== 'object') {
        return { ...DEFAULT_FORECAST_AGGREGATES };
    }

    const record = value as Record<string, unknown>;

    return {
        totalForecast: toNumber(record.total_forecast ?? record.totalForecast),
        totalActual: toNumber(record.total_actual ?? record.totalActual),
        mape: toNumber(record.mape),
        mae: toNumber(record.mae),
        avgDailyDemand: toNumber(
            record.avg_daily_demand ?? record.avgDailyDemand,
        ),
        recommendedReorderPoint: toNumber(
            record.recommended_reorder_point ?? record.recommendedReorderPoint,
        ),
        recommendedSafetyStock: toNumber(
            record.recommended_safety_stock ?? record.recommendedSafetyStock,
        ),
    } satisfies ForecastReportAggregates;
}

function normalizeForecastFilters(value: unknown): ForecastReportFiltersUsed {
    if (!value || typeof value !== 'object') {
        return { ...DEFAULT_FORECAST_FILTERS };
    }

    const record = value as Record<string, unknown>;

    return {
        startDate: readStringField(record, 'start_date', 'startDate'),
        endDate: readStringField(record, 'end_date', 'endDate'),
        bucket:
            readStringField(record, 'bucket') ??
            DEFAULT_FORECAST_FILTERS.bucket,
        partIds: normalizeNumberArray(record.part_ids ?? record.partIds),
        categoryIds: normalizeStringArray(
            record.category_ids ?? record.categoryIds,
        ),
        locationIds: normalizeNumberArray(
            record.location_ids ?? record.locationIds,
        ),
    } satisfies ForecastReportFiltersUsed;
}

function normalizeSupplierSeries(
    value: unknown,
): SupplierPerformanceMetricSeries[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => {
            if (!entry || typeof entry !== 'object') {
                return null;
            }

            const record = entry as Record<string, unknown>;
            const metricName = readStringField(
                record,
                'metric_name',
                'metricName',
            );
            if (!metricName) {
                return null;
            }

            const dataPoints = Array.isArray(record.data)
                ? record.data
                      .map((point) => normalizeSupplierMetricPoint(point))
                      .filter(
                          (point): point is SupplierMetricPoint =>
                              point !== null,
                      )
                : [];

            return {
                metricName,
                label: readStringField(record, 'label') ?? metricName,
                data: dataPoints,
            } satisfies SupplierPerformanceMetricSeries;
        })
        .filter(
            (entry): entry is SupplierPerformanceMetricSeries => entry !== null,
        );
}

function normalizeSupplierMetricPoint(
    entry: unknown,
): SupplierMetricPoint | null {
    if (!entry || typeof entry !== 'object') {
        return null;
    }

    const record = entry as Record<string, unknown>;
    const date = readStringField(record, 'date');

    if (!date) {
        return null;
    }

    return {
        date,
        value: toNumber(record.value),
    };
}

function normalizeSupplierTable(value: unknown): SupplierPerformanceTableRow[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => {
            if (!entry || typeof entry !== 'object') {
                return null;
            }

            const record = entry as Record<string, unknown>;

            return {
                supplierId: normalizeSupplierId(
                    record.supplier_id ?? record.supplierId,
                ),
                supplierName: readStringField(
                    record,
                    'supplier_name',
                    'supplierName',
                ),
                onTimeDeliveryRate: toNumber(
                    record.on_time_delivery_rate ?? record.onTimeDeliveryRate,
                ),
                defectRate: toNumber(record.defect_rate ?? record.defectRate),
                leadTimeVariance: toNumber(
                    record.lead_time_variance ?? record.leadTimeVariance,
                ),
                priceVolatility: toNumber(
                    record.price_volatility ?? record.priceVolatility,
                ),
                serviceResponsiveness: toNumber(
                    record.service_responsiveness ??
                        record.serviceResponsiveness,
                ),
                riskScore: (() => {
                    const numeric = Number(
                        record.risk_score ?? record.riskScore,
                    );
                    return Number.isFinite(numeric) ? numeric : null;
                })(),
                riskCategory: readStringField(
                    record,
                    'risk_category',
                    'riskCategory',
                ),
            } satisfies SupplierPerformanceTableRow;
        })
        .filter((row): row is SupplierPerformanceTableRow => row !== null);
}

function normalizeSupplierFilters(
    value: unknown,
): SupplierPerformanceFiltersUsed {
    if (!value || typeof value !== 'object') {
        return { ...DEFAULT_SUPPLIER_FILTERS };
    }

    const record = value as Record<string, unknown>;

    return {
        startDate: readStringField(record, 'start_date', 'startDate'),
        endDate: readStringField(record, 'end_date', 'endDate'),
        bucket:
            readStringField(record, 'bucket') ??
            DEFAULT_SUPPLIER_FILTERS.bucket,
        supplierId: normalizeSupplierId(
            record.supplier_id ?? record.supplierId,
        ),
    } satisfies SupplierPerformanceFiltersUsed;
}

function normalizeReportSummary(payload: unknown): ReportSummary {
    if (!payload || typeof payload !== 'object') {
        return { ...DEFAULT_REPORT_SUMMARY };
    }

    const record = payload as Record<string, unknown>;
    const bullets = Array.isArray(record.bullets)
        ? record.bullets
              .map((entry) => (typeof entry === 'string' ? entry.trim() : ''))
              .filter((entry) => entry.length > 0)
        : [];

    return {
        summaryMarkdown:
            readStringField(record, 'summary_markdown', 'summaryMarkdown') ??
            '',
        bullets,
        source:
            readStringField(record, 'source') ?? DEFAULT_REPORT_SUMMARY.source,
        provider:
            readStringField(record, 'provider') ??
            DEFAULT_REPORT_SUMMARY.provider,
    } satisfies ReportSummary;
}

function sanitizeDateInput(value?: string | null): string | undefined {
    if (typeof value !== 'string') {
        return undefined;
    }

    const trimmed = value.trim();
    return trimmed.length > 0 ? trimmed : undefined;
}

function normalizeNumberArray(value: unknown): number[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => {
            if (typeof entry === 'number') {
                return Number.isFinite(entry) ? entry : null;
            }

            if (typeof entry === 'string') {
                const trimmed = entry.trim();
                if (trimmed === '') {
                    return null;
                }
                const parsed = Number(trimmed);
                return Number.isFinite(parsed) ? parsed : null;
            }

            return null;
        })
        .filter((entry): entry is number => entry !== null);
}

function normalizeStringArray(value: unknown): string[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((entry) => (typeof entry === 'string' ? entry.trim() : ''))
        .filter((entry) => entry.length > 0);
}

function readStringField(
    record: Record<string, unknown>,
    ...keys: string[]
): string | null {
    for (const key of keys) {
        const value = record[key];
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed.length > 0) {
                return trimmed;
            }
        }
    }

    return null;
}

function createEmptyForecastReportResult(): ForecastReportResult {
    return {
        report: {
            series: [],
            table: [],
            aggregates: { ...DEFAULT_FORECAST_AGGREGATES },
            filtersUsed: { ...DEFAULT_FORECAST_FILTERS },
        },
        summary: { ...DEFAULT_REPORT_SUMMARY },
    };
}

function createEmptySupplierReportResult(): SupplierPerformanceReportResult {
    return {
        report: {
            series: [],
            table: [],
            filtersUsed: { ...DEFAULT_SUPPLIER_FILTERS },
        },
        summary: { ...DEFAULT_REPORT_SUMMARY },
    };
}
