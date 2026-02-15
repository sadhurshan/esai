import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, OrdersAppApi } from '@/sdk';
import type {
    CursorPaginated,
    SalesOrderSummary,
    SupplierOrderFilters,
} from '@/types/orders';

export type SupplierOrderListResult = CursorPaginated<SalesOrderSummary>;

export type UseSupplierOrdersParams = SupplierOrderFilters;

const DEFAULT_PER_PAGE = 25;

export function useSupplierOrders(
    params: UseSupplierOrdersParams = {},
): UseQueryResult<SupplierOrderListResult, HttpError> {
    const ordersApi = useSdkClient(OrdersAppApi);
    const queryFilters = {
        ...params,
        perPage: params.perPage ?? DEFAULT_PER_PAGE,
    } satisfies SupplierOrderFilters;

    return useQuery<SupplierOrderListResult, HttpError>({
        queryKey: queryKeys.orders.supplierList(queryFilters),
        placeholderData: keepPreviousData,
        queryFn: async () => ordersApi.listSupplierOrders(queryFilters),
        staleTime: 15_000,
        gcTime: 60_000,
    });
}
