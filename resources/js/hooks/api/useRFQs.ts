import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Paged, RFQ } from '@/types/sourcing';

export interface RFQListParams extends Record<string, unknown> {
    tab?: 'sent' | 'received' | 'open';
    q?: string;
    sort?: 'sent_at' | 'deadline_at';
    sort_direction?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
}

type RFQListResponse = Paged<{
    id: number;
    number: string;
    item_name: string;
    type: string;
    quantity: number;
    material: string;
    method: string;
    status: string;
    client_company: string;
    deadline_at: string | null;
    sent_at: string | null;
    is_open_bidding: boolean;
}>;

const mapRFQ = (payload: RFQListResponse['items'][number]): RFQ => ({
    id: payload.id,
    rfqNumber: payload.number,
    title: payload.item_name,
    method: payload.method,
    material: payload.material,
    quantity: payload.quantity,
    dueDate: payload.deadline_at ?? '',
    status: payload.status,
    companyName: payload.client_company,
    openBidding: Boolean(payload.is_open_bidding),
    items: [],
});

type RFQListResult = { items: RFQ[]; meta: RFQListResponse['meta'] };

export function useRFQs(params: RFQListParams = {}): UseQueryResult<RFQListResult, ApiError> {
    return useQuery<RFQListResult, ApiError, RFQListResult>({
        queryKey: queryKeys.rfqs.list(params),
        queryFn: async () => {
            const query = buildQuery(params);
            const response = (await api.get<RFQListResponse>(`/rfqs${query}`)) as unknown as RFQListResponse;

            return {
                items: response.items.map(mapRFQ),
                meta: response.meta,
            };
        },
        staleTime: 30_000,
        placeholderData: keepPreviousData,
    });
}
