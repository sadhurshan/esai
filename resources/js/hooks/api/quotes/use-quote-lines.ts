import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import type {
    HttpError,
    Quote,
    QuoteLineRequest,
    QuoteLineUpdateRequest,
    SubmitQuote201Response,
} from '@/sdk';
import { QuotesApi } from '@/sdk';

import { invalidateQuoteQueries } from './query-invalidation';

interface BaseLineVariables {
    quoteId: string | number;
    rfqId?: string | number;
}

export interface AddQuoteLineVariables extends BaseLineVariables {
    payload: QuoteLineRequest;
}

export interface UpdateQuoteLineVariables extends BaseLineVariables {
    quoteItemId: string | number;
    payload: QuoteLineUpdateRequest;
}

export interface DeleteQuoteLineVariables extends BaseLineVariables {
    quoteItemId: string | number;
}

export interface UseQuoteLinesResult {
    addLine: UseMutationResult<Quote, HttpError, AddQuoteLineVariables>;
    updateLine: UseMutationResult<Quote, HttpError, UpdateQuoteLineVariables>;
    deleteLine: UseMutationResult<Quote, HttpError, DeleteQuoteLineVariables>;
}

export function useQuoteLines(): UseQuoteLinesResult {
    const quotesApi = useSdkClient(QuotesApi);
    const queryClient = useQueryClient();

    const handleSuccess = (quote: Quote, rfqId?: string | number) => {
        invalidateQuoteQueries(queryClient, {
            quoteId: quote.id,
            rfqId: rfqId ?? quote.rfqId,
            invalidateSupplierLists: true,
        });
    };

    const addLine = useMutation<Quote, HttpError, AddQuoteLineVariables>({
        mutationFn: async ({ quoteId, payload }) => {
            const response: SubmitQuote201Response =
                await quotesApi.addQuoteLine({
                    quoteId: String(quoteId),
                    quoteLineRequest: payload,
                });

            return response.data;
        },
        onSuccess: (quote, variables) => {
            handleSuccess(quote, variables.rfqId);
        },
    });

    const updateLine = useMutation<Quote, HttpError, UpdateQuoteLineVariables>({
        mutationFn: async ({ quoteId, quoteItemId, payload }) => {
            const response: SubmitQuote201Response =
                await quotesApi.updateQuoteLine({
                    quoteId: String(quoteId),
                    quoteItemId: String(quoteItemId),
                    quoteLineUpdateRequest: payload,
                });

            return response.data;
        },
        onSuccess: (quote, variables) => {
            handleSuccess(quote, variables.rfqId);
        },
    });

    const deleteLine = useMutation<Quote, HttpError, DeleteQuoteLineVariables>({
        mutationFn: async ({ quoteId, quoteItemId }) => {
            const response: SubmitQuote201Response =
                await quotesApi.deleteQuoteLine({
                    quoteId: String(quoteId),
                    quoteItemId: String(quoteItemId),
                });

            return response.data;
        },
        onSuccess: (quote, variables) => {
            handleSuccess(quote, variables.rfqId);
        },
    });

    return {
        addLine,
        updateLine,
        deleteLine,
    };
}
