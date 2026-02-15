import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { waitFor } from '@testing-library/dom';
import { renderHook } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { useCreateMovement } from '../use-create-movement';

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
        return (
            <QueryClientProvider client={queryClient}>
                {children}
            </QueryClientProvider>
        );
    }

    return { Wrapper, invalidateSpy };
}

describe('useCreateMovement', () => {
    const createMovement = vi.fn();

    beforeEach(() => {
        createMovement.mockReset();
        publishToastMock.mockReset();
        useSdkClientMock.mockReturnValue({
            createMovement,
        });
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('posts a stock movement and invalidates downstream queries', async () => {
        const apiResponse = {
            movement: {
                id: 'move-101',
                number: 'MV-101',
                type: 'receipt',
                lines: [
                    {
                        id: 'line-1',
                        item_id: 'item-1',
                        qty: 5,
                        to_location: {
                            id: 'loc-b',
                            name: 'Main',
                        },
                    },
                ],
            },
        } satisfies Record<string, unknown>;

        createMovement.mockResolvedValue(apiResponse);

        const { Wrapper, invalidateSpy } = createWrapper();
        const { result } = renderHook(() => useCreateMovement(), {
            wrapper: Wrapper,
        });

        const movedAt = new Date('2024-01-01T12:00:00Z').toISOString();

        await result.current.mutateAsync({
            type: 'RECEIPT',
            movedAt,
            lines: [
                {
                    itemId: 'item-1',
                    qty: 5,
                    toLocationId: 'loc-b',
                },
            ],
        });

        expect(createMovement).toHaveBeenCalledWith({
            type: 'RECEIPT',
            movedAt,
            lines: [
                {
                    itemId: 'item-1',
                    qty: 5,
                    uom: undefined,
                    fromLocationId: undefined,
                    toLocationId: 'loc-b',
                    reason: undefined,
                },
            ],
            reference: undefined,
            notes: undefined,
        });

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'success',
            title: 'Movement posted',
        });

        expect(invalidateSpy).toHaveBeenCalledWith({
            queryKey: queryKeys.inventory.movementsList({}),
        });
        expect(invalidateSpy).toHaveBeenCalledWith({
            queryKey: queryKeys.inventory.movement('move-101'),
        });
        expect(invalidateSpy).toHaveBeenCalledWith({
            queryKey: queryKeys.inventory.items({}),
        });
        expect(invalidateSpy).toHaveBeenCalledWith({
            queryKey: queryKeys.inventory.lowStock({}),
        });
    });

    it('enforces transfer location rules before calling the API', async () => {
        const { Wrapper } = createWrapper();
        const { result } = renderHook(() => useCreateMovement(), {
            wrapper: Wrapper,
        });

        await expect(
            result.current.mutateAsync({
                type: 'TRANSFER',
                movedAt: new Date().toISOString(),
                lines: [
                    {
                        itemId: 'item-1',
                        qty: 1,
                        fromLocationId: 'loc-a',
                        toLocationId: 'loc-a',
                    },
                ],
            }),
        ).rejects.toThrow('Line 1 cannot transfer to the same location.');

        expect(createMovement).not.toHaveBeenCalled();

        await waitFor(() => expect(publishToastMock).toHaveBeenCalled());
        expect(publishToastMock.mock.calls[0]?.[0]).toMatchObject({
            variant: 'destructive',
            title: 'Movement failed',
        });
    });
});
