import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import type { HttpError, Quote, SubmitQuote201Response, SubmitQuoteRequest } from '@/sdk';
import { QuotesApi, SubmitQuoteRequestStatusEnum } from '@/sdk';

import { invalidateQuoteQueries } from './query-invalidation';

export type CreateQuoteInput = SubmitQuoteRequest;

export function useCreateQuote(): UseMutationResult<Quote, HttpError, CreateQuoteInput> {
    const quotesApi = useSdkClient(QuotesApi);
    const queryClient = useQueryClient();

    return useMutation<Quote, HttpError, CreateQuoteInput>({
        mutationFn: async (input) => {
            const payload: SubmitQuoteRequest = {
                ...input,
                status: input.status ?? SubmitQuoteRequestStatusEnum.Draft,
            };

            const response: SubmitQuote201Response = await quotesApi.submitQuote({
                submitQuoteRequest: payload,
            });

            return response.data;
        },
        onSuccess: (quote) => {
            invalidateQuoteQueries(queryClient, {
                quoteId: quote.id,
                rfqId: quote.rfqId,
                invalidateSupplierLists: true,
            });
        },
    });
}
