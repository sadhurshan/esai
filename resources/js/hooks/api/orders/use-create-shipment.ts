import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { HttpError, OrdersAppApi } from '@/sdk';
import type { CreateShipmentPayload, SalesOrderDetail } from '@/types/orders';

import { invalidateOrderCaches } from './utils';

export interface CreateShipmentInput extends CreateShipmentPayload {
    orderId: string | number;
}

export function useCreateShipment(): UseMutationResult<
    SalesOrderDetail,
    HttpError,
    CreateShipmentInput
> {
    const queryClient = useQueryClient();
    const ordersApi = useSdkClient(OrdersAppApi);

    return useMutation<SalesOrderDetail, HttpError, CreateShipmentInput>({
        mutationFn: async ({ orderId, ...payload }) =>
            ordersApi.createShipment(orderId, payload),
        onSuccess: (data, variables) => {
            const latestShipment =
                Array.isArray(data.shipments) && data.shipments.length > 0
                    ? data.shipments[data.shipments.length - 1]
                    : undefined;
            publishToast({
                variant: 'success',
                title: 'Shipment created',
                description: latestShipment?.shipmentNo
                    ? `Shipment ${latestShipment.shipmentNo} is now in progress.`
                    : 'Shipment has been queued for fulfillment.',
            });

            invalidateOrderCaches(queryClient, {
                orderId: data.id ?? variables.orderId,
                purchaseOrderId: data.poId,
            });
        },
    });
}
