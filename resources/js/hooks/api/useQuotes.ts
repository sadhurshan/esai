import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { QuoteSummary } from '@/types/sourcing';
import { mapQuote, type RFQDetailResponse } from './useRFQ';

interface QuoteListResponse {
    items: RFQDetailResponse['quotes'];
    meta: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
}

export interface UseQuotesParams extends Record<string, unknown> {
    page?: number;
    per_page?: number;
    sort?: 'created_at' | 'unit_price' | 'lead_time_days';
    sort_direction?: 'asc' | 'desc';
}

interface UseQuotesResult {
    items: QuoteSummary[];
    meta: QuoteListResponse['meta'];
}

export function useQuotes(
    rfqId: number,
    params: UseQuotesParams = {},
): UseQueryResult<UseQuotesResult, ApiError> {
    return useQuery<QuoteListResponse, ApiError, UseQuotesResult>({
        queryKey: [...queryKeys.rfqs.quotes(rfqId), params],
        enabled: Number.isFinite(rfqId) && rfqId > 0,
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const query = buildQuery(params);
            return (await api.get<QuoteListResponse>(
                `/rfqs/${rfqId}/quotes${query}`,
            )) as unknown as QuoteListResponse;
        },
        select: (response) => ({
            items: (response.items ?? []).map((quote) => mapQuote(quote)),
            meta: response.meta,
        }),
        staleTime: 30_000,
    });
}
