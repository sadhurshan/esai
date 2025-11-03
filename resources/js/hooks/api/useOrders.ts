import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Order, Paged } from '@/types/sourcing';

export interface OrderListParams extends Record<string, unknown> {
    tab?: 'requested' | 'received';
    status?: 'pending' | 'confirmed' | 'in_production' | 'delivered' | 'cancelled';
    date_from?: string;
    date_to?: string;
    sort?: 'ordered_at';
    page?: number;
    per_page?: number;
}

type OrderListResponse = Paged<{
    id: number;
    number: string;
    party_type: string;
    party_name: string;
    item_name: string;
    quantity: number;
    total_usd: number;
    ordered_at: string | null;
    status: string;
}>;

const mapOrder = (payload: OrderListResponse['items'][number]): Order => ({
    id: payload.id,
    orderNumber: payload.number,
    party: payload.party_name,
    item: payload.item_name,
    quantity: payload.quantity,
    totalUsd: payload.total_usd,
    orderDate: payload.ordered_at ?? '',
    status: payload.status,
});

type OrderListResult = { items: Order[]; meta: OrderListResponse['meta'] };

export function useOrders(params: OrderListParams = {}): UseQueryResult<OrderListResult, ApiError> {
    return useQuery<OrderListResult, ApiError, OrderListResult>({
        queryKey: queryKeys.orders.list(params),
        queryFn: async () => {
            const query = buildQuery(params);
            const response = (await api.get<OrderListResponse>(`/orders${query}`)) as unknown as OrderListResponse;

            return {
                items: response.items.map(mapOrder),
                meta: response.meta,
            };
        },
        staleTime: 30_000,
        placeholderData: keepPreviousData,
    });
}
