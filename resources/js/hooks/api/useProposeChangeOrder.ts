import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderChangeOrder } from '@/types/sourcing';
import { mapChangeOrder, type PoChangeOrderResponse } from './usePurchaseOrder';

export interface ProposeChangeOrderInput {
    purchaseOrderId: number;
    reason: string;
    changes: Record<string, unknown>;
}

export function useProposeChangeOrder(): UseMutationResult<
    PurchaseOrderChangeOrder,
    ApiError,
    ProposeChangeOrderInput
> {
    const queryClient = useQueryClient();

    return useMutation<
        PurchaseOrderChangeOrder,
        ApiError,
        ProposeChangeOrderInput
    >({
        mutationFn: async ({ purchaseOrderId, reason, changes }) => {
            const response = (await api.post<PoChangeOrderResponse>(
                `/purchase-orders/${purchaseOrderId}/change-orders`,
                {
                    reason,
                    changes_json: changes,
                },
            )) as unknown as PoChangeOrderResponse;

            return mapChangeOrder(response);
        },
        onSuccess: (_, variables) => {
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.changeOrders(
                    variables.purchaseOrderId,
                ),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.detail(
                    variables.purchaseOrderId,
                ),
            });
        },
    });
}
