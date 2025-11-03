import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderDetail } from '@/types/sourcing';
import {
    mapChangeOrder,
    mapPurchaseOrder,
    mapPurchaseOrderLine,
    type PurchaseOrderResponse,
} from './usePurchaseOrder';

export interface ApproveChangeOrderInput {
    changeOrderId: number;
    purchaseOrderId: number;
}

export function useApproveChangeOrder(): UseMutationResult<
    PurchaseOrderDetail,
    ApiError,
    ApproveChangeOrderInput
> {
    const queryClient = useQueryClient();

    return useMutation<PurchaseOrderDetail, ApiError, ApproveChangeOrderInput>({
        mutationFn: async ({ changeOrderId }) => {
            const response = (await api.put<PurchaseOrderResponse>(
                `/change-orders/${changeOrderId}/approve`,
            )) as unknown as PurchaseOrderResponse;

            return {
                ...mapPurchaseOrder(response),
                lines: (response.lines ?? []).map(mapPurchaseOrderLine),
                changeOrders: (response.change_orders ?? []).map(mapChangeOrder),
            };
        },
        onSuccess: (data, variables) => {
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.changeOrders(variables.purchaseOrderId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.detail(variables.purchaseOrderId),
            });
            queryClient.setQueryData(queryKeys.purchaseOrders.detail(variables.purchaseOrderId), data);
        },
    });
}
