import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import type { HttpError, Quote, SubmitQuote201Response } from '@/sdk';
import { QuotesApi } from '@/sdk';

import { invalidateQuoteQueries } from './query-invalidation';

export interface SubmitQuoteVariables {
    quoteId: string | number;
    rfqId?: string | number;
}

export function useSubmitQuote(): UseMutationResult<Quote, HttpError | Error, SubmitQuoteVariables> {
    const quotesApi = useSdkClient(QuotesApi);
    const queryClient = useQueryClient();

    return useMutation<Quote, HttpError | Error, SubmitQuoteVariables>({
        mutationFn: async ({ quoteId }) => {
            const response: SubmitQuote201Response = await quotesApi.submitDraftQuote({
                quoteId: String(quoteId),
            });

            if (!response?.data) {
                throw new Error('Quote submission response was missing quote data.');
            }

            return response.data;
        },
        onSuccess: (quote, variables) => {
            invalidateQuoteQueries(queryClient, {
                quoteId: quote.id,
                rfqId: variables?.rfqId ?? quote.rfqId,
                invalidateSupplierLists: true,
            });
        },
    });
}
