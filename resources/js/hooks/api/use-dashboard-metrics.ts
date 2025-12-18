import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useSdkClient } from '@/contexts/api-client-context';
import { DashboardApi, type DashboardMetricsResponse } from '@/sdk';
import { queryKeys } from '@/lib/queryKeys';

export interface DashboardMetrics {
    openRfqCount: number;
    quotesAwaitingReviewCount: number;
    posAwaitingAcknowledgementCount: number;
    unpaidInvoiceCount: number;
    lowStockPartCount: number;
}

const FALLBACK_METRICS: DashboardMetrics = Object.freeze({
    openRfqCount: 0,
    quotesAwaitingReviewCount: 0,
    posAwaitingAcknowledgementCount: 0,
    unpaidInvoiceCount: 0,
    lowStockPartCount: 0,
});

function normalizeMetrics(response?: DashboardMetricsResponse | null): DashboardMetrics {
    const payload = response?.data;

    return {
        openRfqCount: Number(payload?.open_rfq_count ?? 0),
        quotesAwaitingReviewCount: Number(payload?.quotes_awaiting_review_count ?? 0),
        posAwaitingAcknowledgementCount: Number(payload?.pos_awaiting_acknowledgement_count ?? 0),
        unpaidInvoiceCount: Number(payload?.unpaid_invoice_count ?? 0),
        lowStockPartCount: Number(payload?.low_stock_part_count ?? 0),
    } satisfies DashboardMetrics;
}

export function useDashboardMetrics(enabled: boolean): UseQueryResult<DashboardMetrics, unknown> {
    const dashboardApi = useSdkClient(DashboardApi);

    return useQuery<DashboardMetrics>({
        queryKey: queryKeys.dashboard.metrics(),
        enabled,
        queryFn: async () => {
            const response = await dashboardApi.getMetrics();

            return normalizeMetrics(response);
        },
        staleTime: 30_000,
        placeholderData: FALLBACK_METRICS,
    });
}
