import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useSdkClient } from '@/contexts/api-client-context';
import { ListRfqsTabEnum, PurchaseOrdersApi, RFQsApi, type RfqCollection } from '@/sdk';

export interface DashboardMetrics {
    openRfqCount: number;
    quotesAwaitingReviewCount: number;
    posAwaitingAcknowledgementCount: number;
    unpaidInvoiceCount: number;
    lowStockPartCount: number;
}

const FALLBACK_METRICS: DashboardMetrics = {
    openRfqCount: 0,
    quotesAwaitingReviewCount: 0,
    posAwaitingAcknowledgementCount: 0,
    unpaidInvoiceCount: 0,
    lowStockPartCount: 0,
};

function extractTotal(collection?: RfqCollection | null): number {
    if (!collection) {
        return 0;
    }

    return collection.meta?.total ?? collection.items.length ?? 0;
}

export function useDashboardMetrics(enabled: boolean): UseQueryResult<DashboardMetrics, unknown> {
    const rfqsApi = useSdkClient(RFQsApi);
    const purchaseOrdersApi = useSdkClient(PurchaseOrdersApi);

    return useQuery<DashboardMetrics>({
        queryKey: ['dashboard', 'metrics'],
        enabled,
        queryFn: async () => {
            const [openRfqsResponse, quotesAwaitingReviewResponse, purchaseOrdersResponse] = await Promise.all([
                rfqsApi.listRfqs({ perPage: 1, tab: ListRfqsTabEnum.Open }),
                rfqsApi.listRfqs({ perPage: 1, tab: ListRfqsTabEnum.Received }),
                purchaseOrdersApi.listPurchaseOrders({ perPage: 1, status: 'sent' }),
            ]);

            const openRfqCount = extractTotal(openRfqsResponse.data);
            const quotesAwaitingReviewCount = extractTotal(quotesAwaitingReviewResponse.data);
            const posAwaitingAcknowledgementCount = purchaseOrdersResponse.data.meta?.total ?? 0;

            return {
                openRfqCount,
                quotesAwaitingReviewCount,
                posAwaitingAcknowledgementCount,
                unpaidInvoiceCount: 0, // TODO: clarify invoicing summary endpoint once available
                lowStockPartCount: 0, // TODO: wire low-stock metric when inventory summary endpoint ships
            } satisfies DashboardMetrics;
        },
        staleTime: 30_000,
        placeholderData: FALLBACK_METRICS,
    });
}
