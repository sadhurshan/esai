import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

export interface SupplierDashboardMetrics {
    rfqInvitationCount: number;
    quotesDraftCount: number;
    quotesSubmittedCount: number;
    purchaseOrdersPendingAckCount: number;
    invoicesUnpaidCount: number;
}

const FALLBACK_METRICS: SupplierDashboardMetrics = Object.freeze({
    rfqInvitationCount: 0,
    quotesDraftCount: 0,
    quotesSubmittedCount: 0,
    purchaseOrdersPendingAckCount: 0,
    invoicesUnpaidCount: 0,
});

interface SupplierDashboardMetricsResponse {
    rfq_invitation_count: number;
    quotes_draft_count: number;
    quotes_submitted_count: number;
    purchase_orders_pending_ack_count: number;
    invoices_unpaid_count: number;
}

function normalizeMetrics(payload?: SupplierDashboardMetricsResponse | null): SupplierDashboardMetrics {
    if (!payload) {
        return FALLBACK_METRICS;
    }

    return {
        rfqInvitationCount: Number(payload.rfq_invitation_count ?? 0),
        quotesDraftCount: Number(payload.quotes_draft_count ?? 0),
        quotesSubmittedCount: Number(payload.quotes_submitted_count ?? 0),
        purchaseOrdersPendingAckCount: Number(payload.purchase_orders_pending_ack_count ?? 0),
        invoicesUnpaidCount: Number(payload.invoices_unpaid_count ?? 0),
    } satisfies SupplierDashboardMetrics;
}

export function useSupplierDashboardMetrics(enabled: boolean): UseQueryResult<SupplierDashboardMetrics, ApiError> {
    return useQuery<SupplierDashboardMetrics>({
        queryKey: queryKeys.dashboard.supplierMetrics(),
        enabled,
        queryFn: async () => {
            const { data } = await api.get<SupplierDashboardMetricsResponse>('/supplier/dashboard/metrics');

            return normalizeMetrics(data);
        },
        placeholderData: FALLBACK_METRICS,
        staleTime: 30_000,
    });
}
