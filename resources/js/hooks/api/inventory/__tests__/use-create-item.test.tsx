import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useCreateItem } from '../use-create-item';
import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError } from '@/sdk';

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

describe('useCreateItem', () => {
    const createItem = vi.fn();

    beforeEach(() => {
        createItem.mockReset();
        publishToastMock.mockReset();
        useSdkClientMock.mockReturnValue({
            createItem,
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('creates an item and invalidates inventory caches', async () => {
        const apiResponse = {
            item: {
                id: 'item-1',
                sku: 'SKU-1',
                name: 'Widget',
                uom: 'EA',
            },
        } satisfies Record<string, unknown>;

        createItem.mockResolvedValue(apiResponse);

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useCreateItem(), { wrapper: Wrapper });

        await result.current.mutateAsync({
            sku: '  SKU-1  ',
            name: ' Widget ',
            uom: ' ea ',
            category: '  Brackets  ',
        });

        expect(createItem).toHaveBeenCalledWith({
            sku: 'SKU-1',
            name: 'Widget',
            uom: 'ea',
            category: 'Brackets',
            minStock: undefined,
            reorderQty: undefined,
            leadTimeDays: undefined,
            active: true,
            description: undefined,
            attributes: undefined,
            defaultLocationId: undefined,
        });

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'success',
            title: 'Item created',
        });

        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: queryKeys.inventory.items({}) });
        expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: queryKeys.inventory.item('item-1') });
    });

    it('rejects when required fields are missing', async () => {
        const { Wrapper } = createWrapper();
        const { result } = renderHook(() => useCreateItem(), { wrapper: Wrapper });

        await expect(
            result.current.mutateAsync({
                sku: '   ',
                name: '',
                uom: '',
            }),
        ).rejects.toThrow('SKU, name, and default UoM are required.');

        expect(createItem).not.toHaveBeenCalled();

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'destructive',
            title: 'Unable to create item',
        });
    });

    it('surfaces server validation errors in the toast', async () => {
        const apiBody = {
            status: 'error',
            message: '',
            errors: {
                sku: ['SKU already exists'],
            },
        } satisfies Record<string, unknown>;

        const httpError = new HttpError(
            new Response(JSON.stringify(apiBody), {
                status: 422,
                headers: { 'Content-Type': 'application/json' },
            }),
            apiBody,
        );

        createItem.mockRejectedValue(httpError);

        const { Wrapper } = createWrapper();
        const { result } = renderHook(() => useCreateItem(), { wrapper: Wrapper });

        await expect(
            result.current.mutateAsync({
                sku: 'SKU-55',
                name: 'Widget',
                uom: 'EA',
            }),
        ).rejects.toBeInstanceOf(HttpError);

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'destructive',
            title: 'Unable to create item',
            description: 'SKU already exists',
        });
    });
});
