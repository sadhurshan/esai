import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { HttpError, OrdersAppApi } from '@/sdk';
import type { AckOrderPayload, SalesOrderDetail } from '@/types/orders';

import { invalidateOrderCaches } from './utils';

export interface AckOrderInput extends AckOrderPayload {
    orderId: string | number;
}

export function useAckOrder(): UseMutationResult<
    SalesOrderDetail,
    HttpError,
    AckOrderInput
> {
    const queryClient = useQueryClient();
    const ordersApi = useSdkClient(OrdersAppApi);

    return useMutation<SalesOrderDetail, HttpError, AckOrderInput>({
        mutationFn: async ({ orderId, decision, reason }) =>
            ordersApi.acknowledgeOrder(orderId, { decision, reason }),
        onSuccess: (data, variables) => {
            publishToast({
                variant: 'success',
                title:
                    variables.decision === 'accept'
                        ? 'Order accepted'
                        : 'Order declined',
                description:
                    variables.decision === 'accept'
                        ? 'Purchase order acknowledgement saved. You can begin fulfillment.'
                        : 'Buyer has been notified about the decline.',
            });

            invalidateOrderCaches(queryClient, {
                orderId: data.id ?? variables.orderId,
                purchaseOrderId: data.poId,
            });
        },
    });
}
