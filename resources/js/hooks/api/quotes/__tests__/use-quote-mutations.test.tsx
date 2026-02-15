import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    QuoteStatusEnum,
    type ApiSuccessResponse,
    type Quote,
    type SubmitQuote201Response,
    type SubmitQuoteRevisionRequest,
} from '@/sdk';
import { useReviseQuote } from '../use-revise-quote';
import { useSubmitQuote } from '../use-submit-quote';
import { useWithdrawQuote } from '../use-withdraw-quote';

vi.mock('@/contexts/api-client-context', () => ({
    useSdkClient: vi.fn(),
}));

const useSdkClientMock = vi.mocked(useSdkClient);

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
            mutations: {
                retry: false,
            },
        },
    });

    const invalidateSpy = vi.spyOn(queryClient, 'invalidateQueries');

    function Wrapper({ children }: PropsWithChildren) {
        return (
            <QueryClientProvider client={queryClient}>
                {children}
            </QueryClientProvider>
        );
    }

    return { Wrapper, invalidateSpy };
}

function createQuote(overrides: Partial<Quote> = {}): Quote {
    return {
        id: 'quote-1',
        rfqId: 101,
        supplierId: 55,
        status: QuoteStatusEnum.Draft,
        currency: 'USD',
        totalMinor: 12500,
        ...overrides,
    };
}

describe('quote mutation hooks', () => {
    afterEach(() => {
        vi.clearAllMocks();
        useSdkClientMock.mockReset();
    });

    it('submits a draft quote and invalidates caches', async () => {
        const quote = createQuote({ status: QuoteStatusEnum.Submitted });
        const submitDraftQuote = vi.fn().mockResolvedValue({
            status: 'success',
            data: quote,
        } as SubmitQuote201Response);

        useSdkClientMock.mockReturnValue({ submitDraftQuote });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useSubmitQuote(), {
            wrapper: Wrapper,
        });

        await result.current.mutateAsync({
            quoteId: quote.id,
            rfqId: quote.rfqId,
        });

        expect(submitDraftQuote).toHaveBeenCalledWith({ quoteId: quote.id });
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                queryKey: queryKeys.quotes.detail(quote.id),
            }),
        );
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                queryKey: queryKeys.rfqs.quotes(quote.rfqId),
            }),
        );
    });

    it('withdraws a quote with a reason and refreshes related caches', async () => {
        const response: ApiSuccessResponse = {
            status: 'success',
            message: 'ok',
        } as ApiSuccessResponse;
        const withdrawQuote = vi.fn().mockResolvedValue(response);
        useSdkClientMock.mockReturnValue({ withdrawQuote });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useWithdrawQuote(), {
            wrapper: Wrapper,
        });

        await result.current.mutateAsync({
            quoteId: 'quote-xyz',
            rfqId: 'rfq-42',
            reason: 'Pricing update',
        });

        expect(withdrawQuote).toHaveBeenCalledWith({
            quoteId: 'quote-xyz',
            rfqId: 'rfq-42',
            withdrawQuoteRequest: { reason: 'Pricing update' },
        });
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                queryKey: queryKeys.quotes.detail('quote-xyz'),
            }),
        );
    });

    it('submits a quote revision payload', async () => {
        const revisionPayload: SubmitQuoteRevisionRequest = {
            note: 'Update unit prices',
            items: [],
        };
        const submitQuoteRevision = vi
            .fn()
            .mockResolvedValue({ status: 'success' });
        useSdkClientMock.mockReturnValue({ submitQuoteRevision });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useReviseQuote(), {
            wrapper: Wrapper,
        });

        await result.current.mutateAsync({
            quoteId: 'quote-abc',
            rfqId: 'rfq-abc',
            payload: revisionPayload,
        });

        expect(submitQuoteRevision).toHaveBeenCalledWith({
            quoteId: 'quote-abc',
            rfqId: 'rfq-abc',
            submitQuoteRevisionRequest: revisionPayload,
        });
        expect(invalidateSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                queryKey: queryKeys.quotes.revisions('rfq-abc', 'quote-abc'),
            }),
        );
    });
});
