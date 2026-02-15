import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import type {
    ApiSuccessResponse,
    HttpError,
    SubmitQuoteRevisionRequest,
} from '@/sdk';
import { QuotesApi } from '@/sdk';

import { invalidateQuoteQueries } from './query-invalidation';

export interface ReviseQuoteVariables {
    rfqId: string | number;
    quoteId: string | number;
    payload: SubmitQuoteRevisionRequest;
}

export function useReviseQuote(): UseMutationResult<
    ApiSuccessResponse,
    HttpError,
    ReviseQuoteVariables
> {
    const quotesApi = useSdkClient(QuotesApi);
    const queryClient = useQueryClient();

    return useMutation<ApiSuccessResponse, HttpError, ReviseQuoteVariables>({
        mutationFn: async ({ rfqId, quoteId, payload }) =>
            quotesApi.submitQuoteRevision({
                rfqId: String(rfqId),
                quoteId: String(quoteId),
                submitQuoteRevisionRequest: payload,
            }),
        onSuccess: (_, variables) => {
            invalidateQuoteQueries(queryClient, {
                quoteId: variables.quoteId,
                rfqId: variables.rfqId,
                invalidateSupplierLists: true,
            });
        },
    });
}
