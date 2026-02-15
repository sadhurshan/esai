import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { HttpError, OrdersAppApi } from '@/sdk';
import type {
    SalesOrderDetail,
    UpdateShipmentStatusPayload,
} from '@/types/orders';

import { invalidateOrderCaches } from './utils';

export interface UpdateShipmentStatusInput extends UpdateShipmentStatusPayload {
    shipmentId: string | number;
    orderId: string | number;
}

export function useUpdateShipmentStatus(): UseMutationResult<
    SalesOrderDetail,
    HttpError,
    UpdateShipmentStatusInput
> {
    const queryClient = useQueryClient();
    const ordersApi = useSdkClient<OrdersAppApi>(OrdersAppApi);

    return useMutation<SalesOrderDetail, HttpError, UpdateShipmentStatusInput>({
        mutationFn: async ({ shipmentId, status, deliveredAt }) => {
            if (status === 'delivered' && !deliveredAt) {
                throw new Error(
                    'deliveredAt is required when marking a shipment as delivered',
                );
            }

            return ordersApi.updateShipmentStatus(shipmentId, {
                status,
                deliveredAt,
            });
        },
        onSuccess: (data, variables) => {
            publishToast({
                variant: 'success',
                title:
                    variables.status === 'delivered'
                        ? 'Shipment delivered'
                        : 'Shipment in transit',
                description:
                    variables.status === 'delivered'
                        ? 'Buyer has been notified that goods have arrived.'
                        : 'Shipment progress updated to in transit.',
            });

            invalidateOrderCaches(queryClient, {
                orderId: data.id ?? variables.orderId,
                purchaseOrderId: data.poId,
            });
        },
    });
}
