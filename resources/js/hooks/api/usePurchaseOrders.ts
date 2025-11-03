import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderSummary } from '@/types/sourcing';
import { mapPurchaseOrder, type PurchaseOrderResponse } from './usePurchaseOrder';

interface PurchaseOrderListResponse {
    items: PurchaseOrderResponse[];
    meta: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
}

export interface UsePurchaseOrdersParams extends Record<string, unknown> {
    status?: string | string[];
    page?: number;
    per_page?: number;
}

interface UsePurchaseOrdersResult {
    items: PurchaseOrderSummary[];
    meta: PurchaseOrderListResponse['meta'];
}

export function usePurchaseOrders(
    params: UsePurchaseOrdersParams = {},
): UseQueryResult<UsePurchaseOrdersResult, ApiError> {
    return useQuery<PurchaseOrderListResponse, ApiError, UsePurchaseOrdersResult>({
        queryKey: queryKeys.purchaseOrders.list(params),
        queryFn: async () => {
            const query = buildQuery(params);
            return (await api.get<PurchaseOrderListResponse>(
                `/purchase-orders${query}`,
            )) as unknown as PurchaseOrderListResponse;
        },
        select: (response) => ({
            items: response.items.map((item) => mapPurchaseOrder(item)),
            meta: response.meta,
        }),
        placeholderData: keepPreviousData,
        staleTime: 30_000,
    });
}
