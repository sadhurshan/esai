import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useLowStock } from '../use-low-stock';
import { useSdkClient } from '@/contexts/api-client-context';

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

    return { Wrapper };
}

describe('useLowStock', () => {
    const listLowStock = vi.fn();

    beforeEach(() => {
        listLowStock.mockReset();
        useSdkClientMock.mockReturnValue({
            listLowStock,
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('maps API results into low-stock rows', async () => {
        const apiResponse = {
            items: [
                {
                    item_id: 'item-5',
                    sku: 'SKU-5',
                    name: 'Bearing',
                    on_hand: 2,
                    min_stock: 6,
                    reorder_qty: 12,
                    lead_time_days: 7,
                    uom: 'EA',
                    location_name: 'WH-A',
                    site_name: 'Austin',
                    suggested_reorder_date: '2024-02-01',
                },
            ],
            meta: {
                next_cursor: 'abc',
            },
        } satisfies Record<string, unknown>;

        listLowStock.mockResolvedValue(apiResponse);

        const { Wrapper } = createWrapper();
        const { result } = renderHook(() => useLowStock({ perPage: 50, locationId: 'loc-1' }), {
            wrapper: Wrapper,
        });

        await waitFor(() => expect(result.current.isSuccess).toBe(true));

        expect(listLowStock).toHaveBeenCalledWith({
            cursor: undefined,
            perPage: 50,
            locationId: 'loc-1',
            siteId: undefined,
            category: undefined,
        });

        expect(result.current.data?.items).toEqual([
            expect.objectContaining({
                itemId: 'item-5',
                sku: 'SKU-5',
                suggestedReorderDate: '2024-02-01',
                locationName: 'WH-A',
                siteName: 'Austin',
            }),
        ]);
        expect(result.current.data?.meta).toEqual({ next_cursor: 'abc' });
    });
});
