import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { PurchaseOrdersApi } from '@/sdk';
import type { PurchaseOrderDetail } from '@/types/sourcing';
import { mapPurchaseOrder } from '@/hooks/api/usePurchaseOrder';

export function usePo(poId: number): UseQueryResult<PurchaseOrderDetail, unknown> {
    const purchaseOrdersApi = useSdkClient(PurchaseOrdersApi);

    return useQuery<PurchaseOrderDetail>({
        queryKey: queryKeys.purchaseOrders.detail(poId),
        enabled: Number.isFinite(poId) && poId > 0,
        queryFn: async () => {
            const response = await purchaseOrdersApi.showPurchaseOrder({
                purchaseOrderId: poId,
            });

            const mapped = mapPurchaseOrder(response.data);

            return {
                ...mapped,
                lines: mapped.lines ?? [],
                changeOrders: mapped.changeOrders ?? [],
            };
        },
        staleTime: 15_000,
    });
}
