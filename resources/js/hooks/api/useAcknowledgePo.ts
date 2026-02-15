import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderDetail } from '@/types/sourcing';
import {
    mapChangeOrder,
    mapPurchaseOrder,
    mapPurchaseOrderLine,
    type PurchaseOrderResponse,
} from './usePurchaseOrder';

export interface AcknowledgePoInput {
    purchaseOrderId: number;
    action: 'accept' | 'reject';
}

export function useAcknowledgePo(): UseMutationResult<
    PurchaseOrderDetail,
    ApiError,
    AcknowledgePoInput
> {
    const queryClient = useQueryClient();

    return useMutation<PurchaseOrderDetail, ApiError, AcknowledgePoInput>({
        mutationFn: async ({ purchaseOrderId, action }) => {
            const response = (await api.post<PurchaseOrderResponse>(
                `/purchase-orders/${purchaseOrderId}/acknowledge`,
                { action },
            )) as unknown as PurchaseOrderResponse;

            return {
                ...mapPurchaseOrder(response),
                lines: (response.lines ?? []).map(mapPurchaseOrderLine),
                changeOrders: (response.change_orders ?? []).map(
                    mapChangeOrder,
                ),
            };
        },
        onSuccess: (data, variables) => {
            queryClient.setQueryData(
                queryKeys.purchaseOrders.detail(variables.purchaseOrderId),
                data,
            );
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.root(),
                exact: false,
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.changeOrders(
                    variables.purchaseOrderId,
                ),
            });
        },
    });
}
