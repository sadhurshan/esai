import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type {
    HttpError,
    Quote,
    RequestMeta,
    SubmitQuote201Response,
} from '@/sdk';
import { QuotesApi } from '@/sdk';

export interface UseQuoteResult {
    quote: Quote;
    meta?: RequestMeta;
}

export function useQuote(
    quoteId?: string | number,
    options?: { enabled?: boolean },
): UseQueryResult<UseQuoteResult, HttpError> {
    const quotesApi = useSdkClient(QuotesApi);

    return useQuery<SubmitQuote201Response, HttpError, UseQuoteResult>({
        queryKey: queryKeys.quotes.detail(String(quoteId ?? '')),
        enabled: Boolean(quoteId) && (options?.enabled ?? true),
        queryFn: async () => {
            if (!quoteId) {
                throw new Error('quoteId is required to load quote details');
            }

            return quotesApi.showQuote({ quoteId: String(quoteId) });
        },
        select: (response) => ({
            quote: response.data,
            meta: response.meta,
        }),
        staleTime: 30_000,
    });
}
