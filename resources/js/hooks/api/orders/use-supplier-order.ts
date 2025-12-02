import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { SalesOrderDetail } from '@/types/orders';
import { HttpError, OrdersAppApi } from '@/sdk';

export interface UseSupplierOrderOptions {
    enabled?: boolean;
}

export function useSupplierOrder(
    salesOrderId: string | number | null | undefined,
    options: UseSupplierOrderOptions = {},
): UseQueryResult<SalesOrderDetail, HttpError> {
    const ordersApi = useSdkClient(OrdersAppApi);
    const enabled = options.enabled ?? Boolean(salesOrderId);

    return useQuery<SalesOrderDetail, HttpError>({
        queryKey: queryKeys.orders.supplierDetail(salesOrderId ?? 'undefined'),
        enabled,
        queryFn: async () => {
            if (salesOrderId === null || salesOrderId === undefined) {
                throw new Error('salesOrderId is required to fetch supplier order details');
            }

            return ordersApi.showSupplierOrder(String(salesOrderId));
        },
    });
}
