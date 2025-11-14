import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useRfqs } from '../use-rfqs';
import { useSdkClient } from '@/contexts/api-client-context';
import {
    ListRfqsSortDirectionEnum,
    ListRfqsSortEnum,
    ListRfqsTabEnum,
    RfqStatusEnum,
    RfqTypeEnum,
    type PageMeta,
    type Rfq,
} from '@/sdk';

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

describe('useRfqs', () => {
    afterEach(() => {
        vi.clearAllMocks();
        useSdkClientMock.mockReset();
    });

    it('requests the open tab from the API and returns meta totals', async () => {
        const rfq: Rfq = {
            id: '1',
            number: 'RFQ-001',
            itemName: 'Open RFQ',
            type: RfqTypeEnum.Manufacture,
            quantity: 10,
            material: 'Steel',
            method: 'Laser cutting',
            status: RfqStatusEnum.Open,
            isOpenBidding: true,
            sentAt: new Date('2025-10-01T00:00:00Z'),
        };

        const meta: PageMeta = {
            currentPage: 2,
            perPage: 20,
            total: 42,
            lastPage: 5,
        };

        const listRfqs = vi.fn().mockResolvedValue({
            data: {
                items: [rfq],
                meta,
            },
        });

        useSdkClientMock.mockReturnValue({ listRfqs });

        const { result } = renderHook(
            () =>
                useRfqs({
                    status: 'open',
                    page: 2,
                    perPage: 20,
                    search: 'open rfq',
                }),
            {
                wrapper: createWrapper(),
            },
        );

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        expect(listRfqs).toHaveBeenCalledWith({
            perPage: 20,
            page: 2,
            q: 'open rfq',
            sort: ListRfqsSortEnum.DeadlineAt,
            sortDirection: ListRfqsSortDirectionEnum.Asc,
            tab: ListRfqsTabEnum.Open,
        });

        expect(result.current.items).toHaveLength(1);
        expect(result.current.total).toBe(42);
        expect(result.current.isClientSideFiltered).toBe(false);
    });

    it('filters drafts client-side when necessary', async () => {
        const awaiting: Rfq = {
            id: 'draft-1',
            number: 'RFQ-010',
            itemName: 'Awaiting RFQ',
            type: RfqTypeEnum.Manufacture,
            quantity: 5,
            material: 'Aluminum',
            method: 'CNC machining',
            status: RfqStatusEnum.Awaiting,
            isOpenBidding: false,
            createdAt: new Date('2025-08-01T00:00:00Z'),
        };

        const open: Rfq = {
            id: 'open-1',
            number: 'RFQ-020',
            itemName: 'Open RFQ',
            type: RfqTypeEnum.ReadyMade,
            quantity: 12,
            material: 'Composite',
            method: 'Injection molding',
            status: RfqStatusEnum.Open,
            isOpenBidding: true,
            sentAt: new Date('2025-08-02T00:00:00Z'),
        };

        const listRfqs = vi.fn().mockResolvedValue({
            data: {
                items: [awaiting, open],
                meta: {
                    currentPage: 1,
                    perPage: 10,
                    total: 2,
                    lastPage: 1,
                } satisfies PageMeta,
            },
        });

        useSdkClientMock.mockReturnValue({ listRfqs });

        const { result } = renderHook(
            () =>
                useRfqs({
                    status: 'draft',
                }),
            {
                wrapper: createWrapper(),
            },
        );

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        const callArgs = listRfqs.mock.calls[0]?.[0];
        expect(callArgs?.tab).toBeUndefined();
        expect(result.current.items).toEqual([awaiting]);
        expect(result.current.total).toBe(1);
        expect(result.current.isClientSideFiltered).toBe(true);
    });
});
