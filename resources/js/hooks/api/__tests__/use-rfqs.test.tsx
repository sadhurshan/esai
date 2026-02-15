import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { waitFor } from '@testing-library/dom';
import { renderHook } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useApiClientContext } from '@/contexts/api-client-context';
import {
    ListRfqs200ResponseStatusEnum,
    ListRfqsSortDirectionEnum,
    ListRfqsSortEnum,
    ListRfqsStatusEnum,
    RfqStatusEnum,
    RfqTypeEnum,
    type Rfq,
} from '@/sdk';
import { useRfqs } from '../use-rfqs';

vi.mock('@/contexts/api-client-context', () => ({
    useApiClientContext: vi.fn(),
}));

const useApiClientContextMock = vi.mocked(useApiClientContext);

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
        },
    });

    function Wrapper({ children }: PropsWithChildren) {
        return (
            <QueryClientProvider client={queryClient}>
                {children}
            </QueryClientProvider>
        );
    }

    return Wrapper;
}

describe('useRfqs', () => {
    afterEach(() => {
        vi.clearAllMocks();
        useApiClientContextMock.mockReset();
    });

    it('passes filters to the API and exposes cursor metadata', async () => {
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

        const fetchMock = vi.fn().mockResolvedValue({
            json: vi.fn().mockResolvedValue({
                status: ListRfqs200ResponseStatusEnum.Success,
                data: {
                    items: [rfq],
                    meta: {
                        current_page: 1,
                        per_page: 25,
                        total: 1,
                        last_page: 1,
                    },
                },
                meta: {
                    request_id: 'req-123',
                    cursor: {
                        next_cursor: 'NEXT',
                        prev_cursor: 'PREV',
                        per_page: 25,
                    },
                },
            }),
        });

        useApiClientContextMock.mockReturnValue({
            configuration: {
                basePath: 'https://api.test',
                fetchApi: fetchMock,
            } as never,
            getClient: vi.fn(),
        });

        const { result } = renderHook(
            () =>
                useRfqs({
                    status: 'open',
                    perPage: 25,
                    search: ' open rfq ',
                    dueFrom: '2025-10-01',
                    dueTo: '2025-10-15',
                    cursor: 'CURSOR_TOKEN',
                }),
            {
                wrapper: createWrapper(),
            },
        );

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const calledUrl: string = fetchMock.mock.calls[0][0];

        expect(calledUrl).toContain('per_page=25');
        expect(calledUrl).toContain('cursor=CURSOR_TOKEN');
        expect(calledUrl).toContain(
            `status=${encodeURIComponent(ListRfqsStatusEnum.Open)}`,
        );
        expect(calledUrl).toContain('search=open+rfq');
        expect(calledUrl).toContain('due_from=2025-10-01');
        expect(calledUrl).toContain('due_to=2025-10-15');
        expect(calledUrl).toContain(`sort=${ListRfqsSortEnum.DueAt}`);
        expect(calledUrl).toContain(
            `sort_direction=${ListRfqsSortDirectionEnum.Asc}`,
        );

        expect(result.current.items[0]).toMatchObject({
            id: rfq.id,
            number: rfq.number,
            method: rfq.method,
            status: rfq.status,
        });
        expect(result.current.cursor?.nextCursor).toBe('NEXT');
        expect(result.current.cursor?.prevCursor).toBe('PREV');
    });

    it('sends status filters directly to the server', async () => {
        const rfq: Rfq = {
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

        const fetchMock = vi.fn().mockResolvedValue({
            json: vi.fn().mockResolvedValue({
                status: ListRfqs200ResponseStatusEnum.Success,
                data: {
                    items: [rfq],
                    meta: {
                        current_page: 1,
                        per_page: 10,
                        total: 1,
                        last_page: 1,
                    },
                },
                meta: {
                    request_id: 'req-456',
                    cursor: {
                        next_cursor: null,
                        prev_cursor: null,
                        per_page: 10,
                    },
                },
            }),
        });

        useApiClientContextMock.mockReturnValue({
            configuration: {
                basePath: 'https://api.test',
                fetchApi: fetchMock,
            } as never,
            getClient: vi.fn(),
        });

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

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const calledUrl: string = fetchMock.mock.calls[0][0];
        expect(calledUrl).toContain(
            `status=${encodeURIComponent(ListRfqsStatusEnum.Draft)}`,
        );

        expect(result.current.items[0]).toMatchObject({
            id: rfq.id,
            number: rfq.number,
            method: rfq.method,
            status: rfq.status,
        });
        expect(result.current.cursor?.nextCursor).toBeUndefined();
    });
});
