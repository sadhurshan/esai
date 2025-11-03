import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderChangeOrder } from '@/types/sourcing';
import { mapChangeOrder, type PoChangeOrderResponse } from './usePurchaseOrder';

export interface RejectChangeOrderInput {
    changeOrderId: number;
    purchaseOrderId: number;
}

export function useRejectChangeOrder(): UseMutationResult<
    PurchaseOrderChangeOrder,
    ApiError,
    RejectChangeOrderInput
> {
    const queryClient = useQueryClient();

    return useMutation<PurchaseOrderChangeOrder, ApiError, RejectChangeOrderInput>({
        mutationFn: async ({ changeOrderId }) => {
            const response = (await api.put<PoChangeOrderResponse>(
                `/change-orders/${changeOrderId}/reject`,
            )) as unknown as PoChangeOrderResponse;

            return mapChangeOrder(response);
        },
        onSuccess: (_, variables) => {
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.changeOrders(variables.purchaseOrderId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.detail(variables.purchaseOrderId),
            });
        },
    });
}
