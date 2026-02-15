import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, OrdersAppApi } from '@/sdk';
import type {
    BuyerOrderFilters,
    CursorPaginated,
    SalesOrderSummary,
} from '@/types/orders';

export type BuyerOrderListResult = CursorPaginated<SalesOrderSummary>;

export type UseBuyerOrdersParams = BuyerOrderFilters;

const DEFAULT_PER_PAGE = 25;

export function useBuyerOrders(
    params: UseBuyerOrdersParams = {},
): UseQueryResult<BuyerOrderListResult, HttpError> {
    const ordersApi = useSdkClient(OrdersAppApi);
    const queryFilters = {
        ...params,
        perPage: params.perPage ?? DEFAULT_PER_PAGE,
    } satisfies BuyerOrderFilters;

    return useQuery<BuyerOrderListResult, HttpError>({
        queryKey: queryKeys.orders.buyerList(queryFilters),
        placeholderData: keepPreviousData,
        queryFn: async () => ordersApi.listBuyerOrders(queryFilters),
        staleTime: 15_000,
        gcTime: 60_000,
    });
}
