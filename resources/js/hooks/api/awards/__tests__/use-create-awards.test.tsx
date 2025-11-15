import { renderHook } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useCreateAwards } from '../use-create-awards';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { RfqItemAwardSummary } from '@/sdk';

vi.mock('@/contexts/api-client-context', () => ({
    useSdkClient: vi.fn(),
}));

const publishToastMock = vi.fn();
type PublishToastArgs = [payload: unknown];
vi.mock('@/components/ui/use-toast', () => ({
    publishToast: (...args: PublishToastArgs) => publishToastMock(...args),
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
        return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
    }

    return { Wrapper, invalidateSpy };
}

describe('useCreateAwards', () => {
    afterEach(() => {
        vi.clearAllMocks();
        useSdkClientMock.mockReset();
        publishToastMock.mockReset();
    });

    it('persists awards and invalidates dependent caches', async () => {
        const rfqId = 77;
        const mutationInput = {
            rfqId,
            items: [
                {
                    rfqItemId: 10,
                    quoteItemId: 200,
                    awardedQty: 5,
                },
            ],
        };

        const awards: RfqItemAwardSummary[] = [
            {
                id: 1,
                rfqItemId: 10,
                quoteId: 99,
                quoteItemId: 200,
                supplierId: 44,
                supplierName: 'Alpha Manufacturing',
                awardedQty: 5,
                status: 'draft',
            },
        ];

        const createAwards = vi.fn().mockResolvedValue({
            data: {
                awards,
            },
        });

        useSdkClientMock.mockReturnValue({ createAwards });

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useCreateAwards(), { wrapper: Wrapper });

        await result.current.mutateAsync(mutationInput);

        expect(createAwards).toHaveBeenCalledWith({
            createAwardsRequest: {
                rfqId,
                items: mutationInput.items,
            },
        });

        expect(publishToastMock).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Awards saved', variant: 'success' }),
        );

        const expectedKeys = [
            queryKeys.awards.candidates(rfqId),
            queryKeys.awards.summary(rfqId),
            queryKeys.rfqs.detail(rfqId),
            queryKeys.rfqs.lines(rfqId),
            queryKeys.rfqs.quotes(rfqId),
            queryKeys.quotes.rfq(rfqId),
            queryKeys.quotes.root(),
            queryKeys.purchaseOrders.root(),
        ];

        for (const key of expectedKeys) {
            expect(invalidateSpy).toHaveBeenCalledWith(expect.objectContaining({ queryKey: key }));
        }
    });

    it('rejects when no award selections were supplied', async () => {
        const createAwards = vi.fn();
        useSdkClientMock.mockReturnValue({ createAwards });

        const { Wrapper } = createWrapper();
        const { result } = renderHook(() => useCreateAwards(), { wrapper: Wrapper });

        await expect(
            result.current.mutateAsync({
                rfqId: 101,
                items: [],
            }),
        ).rejects.toThrow('Select at least one RFQ line to award.');

        expect(createAwards).not.toHaveBeenCalled();
    });
});
