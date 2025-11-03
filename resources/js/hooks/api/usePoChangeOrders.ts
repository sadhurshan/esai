import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderChangeOrder } from '@/types/sourcing';
import { mapChangeOrder, type PoChangeOrderResponse } from './usePurchaseOrder';

interface PoChangeOrderListResponse {
    items: PoChangeOrderResponse[];
}

interface PoChangeOrderListResult {
    items: PurchaseOrderChangeOrder[];
}

export function usePoChangeOrders(
    purchaseOrderId: number,
): UseQueryResult<PoChangeOrderListResult, ApiError> {
    return useQuery<PoChangeOrderListResponse, ApiError, PoChangeOrderListResult>({
        queryKey: queryKeys.purchaseOrders.changeOrders(purchaseOrderId),
        enabled: Number.isFinite(purchaseOrderId) && purchaseOrderId > 0,
        queryFn: async () =>
            (await api.get<PoChangeOrderListResponse>(
                `/purchase-orders/${purchaseOrderId}/change-orders`,
            )) as unknown as PoChangeOrderListResponse,
        select: (response) => ({
            items: (response.items ?? []).map(mapChangeOrder),
        }),
        staleTime: 15_000,
    });
}
