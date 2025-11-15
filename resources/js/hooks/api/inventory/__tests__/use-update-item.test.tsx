import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useUpdateItem } from '../use-update-item';
import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';

vi.mock('@/contexts/api-client-context', () => ({
    useSdkClient: vi.fn(),
}));

vi.mock('@/components/ui/use-toast', () => ({
    publishToast: vi.fn(),
}));

const useSdkClientMock = vi.mocked(useSdkClient);
const publishToastMock = vi.mocked(publishToast);

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
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

describe('useUpdateItem', () => {
    const updateItem = vi.fn();

    beforeEach(() => {
        updateItem.mockReset();
        publishToastMock.mockReset();
        useSdkClientMock.mockReturnValue({
            updateItem,
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('updates an item and invalidates caches', async () => {
        const apiResponse = {
            item: {
                id: 'item-9',
                sku: 'SKU-9',
                name: 'Bracket',
                uom: 'SET',
            },
        } satisfies Record<string, unknown>;

        updateItem.mockResolvedValue(apiResponse);

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useUpdateItem(), { wrapper: Wrapper });

        await result.current.mutateAsync({
            id: 'item-9',
            sku: ' SKU-9 ',
            name: ' Bracket ',
            uom: ' set ',
            minStock: 5,
            reorderQty: 10,
        });

        expect(updateItem).toHaveBeenCalledWith('item-9', {
            sku: 'SKU-9',
            name: 'Bracket',
            uom: 'set',
            category: undefined,
            minStock: 5,
            reorderQty: 10,
            leadTimeDays: undefined,
            active: undefined,
            description: undefined,
            attributes: undefined,
            defaultLocationId: undefined,
        });

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'success',
            title: 'Item updated',
        });

        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: queryKeys.inventory.items({}) });
        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: queryKeys.inventory.item('item-9') });
    });

    it('requires an id before calling the API', async () => {
        const { Wrapper } = createWrapper();
        const { result } = renderHook(() => useUpdateItem(), { wrapper: Wrapper });

        await expect(
            result.current.mutateAsync({
                id: '',
                name: 'Widget',
            }),
        ).rejects.toThrow('Item id is required.');

        expect(updateItem).not.toHaveBeenCalled();

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'destructive',
            title: 'Unable to update item',
        });
    });
});
