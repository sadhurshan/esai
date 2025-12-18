import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, ApiError } from '@/lib/api';
import type { Quote } from '@/sdk';

import { invalidateQuoteQueries } from './query-invalidation';

export interface QuoteDraftUpdatePayload {
    currency?: string | null;
    min_order_qty?: number | null;
    lead_time_days?: number | null;
    incoterm?: string | null;
    payment_terms?: string | null;
    note?: string | null;
    attachments?: number[];
}

interface UpdateQuoteDraftVariables {
    rfqId: string | number;
    quoteId: string | number;
    payload: QuoteDraftUpdatePayload;
}

export function useUpdateQuoteDraft(): UseMutationResult<Quote, ApiError | Error, UpdateQuoteDraftVariables> {
    const queryClient = useQueryClient();

    return useMutation<Quote, ApiError | Error, UpdateQuoteDraftVariables>({
        mutationFn: async ({ rfqId, quoteId, payload }) => {
            const quote = (await api.patch<Quote>(`/rfqs/${rfqId}/quotes/${quoteId}/draft`, payload)) as unknown as Quote;

            if (!quote || typeof quote !== 'object') {
                throw new Error('Quote draft response was missing quote details.');
            }

            return quote;
        },
        onSuccess: (quote, variables) => {
            invalidateQuoteQueries(queryClient, {
                quoteId: quote.id ?? variables.quoteId,
                rfqId: variables.rfqId,
                invalidateSupplierLists: true,
            });
        },
    });
}
