import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import type { ApiSuccessResponse, HttpError } from '@/sdk';
import { QuotesApi } from '@/sdk';

import { invalidateQuoteQueries } from './query-invalidation';

export interface WithdrawQuoteVariables {
    rfqId: string | number;
    quoteId: string | number;
    reason: string;
}

export function useWithdrawQuote(): UseMutationResult<ApiSuccessResponse, HttpError, WithdrawQuoteVariables> {
    const quotesApi = useSdkClient(QuotesApi);
    const queryClient = useQueryClient();

    return useMutation<ApiSuccessResponse, HttpError, WithdrawQuoteVariables>({
        mutationFn: async ({ rfqId, quoteId, reason }) => {
            return quotesApi.withdrawQuote({
                rfqId: String(rfqId),
                quoteId: String(quoteId),
                withdrawQuoteRequest: {
                    reason,
                },
            });
        },
        onSuccess: (_, variables) => {
            invalidateQuoteQueries(queryClient, {
                quoteId: variables.quoteId,
                rfqId: variables.rfqId,
                invalidateSupplierLists: true,
            });
        },
    });
}
