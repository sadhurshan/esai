import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useRfq } from '../use-rfq';
import { useSdkClient } from '@/contexts/api-client-context';
import { RfqStatusEnum, RfqTypeEnum, type Rfq } from '@/sdk';

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
        },
    });

    function Wrapper({ children }: PropsWithChildren) {
        return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
    }

    return Wrapper;
}

describe('useRfq', () => {
    afterEach(() => {
        vi.clearAllMocks();
        useSdkClientMock.mockReset();
    });

    it('fetches RFQ detail when an id is provided', async () => {
        const sampleRfq: Rfq = {
            id: '123',
            number: 'RFQ-123',
            itemName: 'Sample RFQ',
            type: RfqTypeEnum.Manufacture,
            quantity: 25,
            material: '6061-T6',
            method: 'CNC machining',
            status: RfqStatusEnum.Open,
            isOpenBidding: true,
            createdAt: new Date('2025-01-01T00:00:00Z'),
        };

        const showRfq = vi.fn().mockResolvedValue({ data: sampleRfq });
        useSdkClientMock.mockReturnValue({ showRfq });

        const { result } = renderHook(() => useRfq('123'), {
            wrapper: createWrapper(),
        });

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        expect(showRfq).toHaveBeenCalledWith({ rfqId: '123' });
        expect(result.current.data).toEqual(sampleRfq);
    });

    it('does not issue a request when the id is missing', async () => {
        const showRfq = vi.fn();
        useSdkClientMock.mockReturnValue({ showRfq });

        const { result } = renderHook(() => useRfq(null), {
            wrapper: createWrapper(),
        });

        expect(result.current.fetchStatus).toBe('idle');
        expect(showRfq).not.toHaveBeenCalled();
    });
});
