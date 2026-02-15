import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, OrdersAppApi } from '@/sdk';
import type { SalesOrderDetail } from '@/types/orders';

export interface UseBuyerOrderOptions {
    enabled?: boolean;
}

export function useBuyerOrder(
    salesOrderId: string | number | null | undefined,
    options: UseBuyerOrderOptions = {},
): UseQueryResult<SalesOrderDetail, HttpError> {
    const ordersApi = useSdkClient(OrdersAppApi);
    const enabled = options.enabled ?? Boolean(salesOrderId);

    return useQuery<SalesOrderDetail, HttpError>({
        queryKey: queryKeys.orders.buyerDetail(salesOrderId ?? 'undefined'),
        enabled,
        queryFn: async () => {
            if (salesOrderId === null || salesOrderId === undefined) {
                throw new Error(
                    'salesOrderId is required to fetch buyer order details',
                );
            }

            return ordersApi.showBuyerOrder(String(salesOrderId));
        },
    });
}
