import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useSdkClient } from '@/contexts/api-client-context';
import { AnalyticsApi, type ApiSuccessResponse } from '@/sdk';
import { queryKeys } from '@/lib/queryKeys';

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

export function useAnalyticsOverview(enabled: boolean): UseQueryResult<AnalyticsOverviewResult, unknown> {
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

function normalizeOverviewResponse(response: ApiSuccessResponse | null): AnalyticsOverviewResult {
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
        from: typeof metaPayload.from === 'string' ? metaPayload.from : undefined,
        to: typeof metaPayload.to === 'string' ? metaPayload.to : undefined,
    };

    return { kpis, charts, meta };
}

function mapSnapshotGroups(payload: unknown): Record<string, SnapshotPayload[]> {
    if (!payload || typeof payload !== 'object') {
        return {};
    }

    return Object.entries(payload as Record<string, unknown>).reduce<Record<string, SnapshotPayload[]>>(
        (acc, [key, value]) => {
            if (Array.isArray(value)) {
                acc[key] = value
                    .map((entry) => normalizeSnapshot(entry))
                    .filter((entry): entry is SnapshotPayload => entry !== null);
            }

            return acc;
        },
        {},
    );
}

function normalizeSnapshot(entry: unknown): SnapshotPayload | null {
    if (!entry || typeof entry !== 'object') {
        return null;
    }

    const record = entry as Record<string, unknown>;

    return {
        type: typeof record.type === 'string' ? record.type : null,
        period_start: typeof record.period_start === 'string' ? record.period_start : null,
        period_end: typeof record.period_end === 'string' ? record.period_end : null,
        value: toNumber(record.value),
        meta: typeof record.meta === 'object' && record.meta !== null ? (record.meta as Record<string, unknown>) : undefined,
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

function mapTopSuppliers(snapshot: SnapshotPayload | null): SupplierSpendPoint[] {
    if (!snapshot?.meta) {
        return [];
    }

    const suppliers = (snapshot.meta as Record<string, unknown>)['top_suppliers'];

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
            supplierName: typeof supplierNameRaw === 'string' && supplierNameRaw.trim().length > 0 ? supplierNameRaw : 'Supplier',
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
