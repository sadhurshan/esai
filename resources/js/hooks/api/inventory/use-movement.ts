import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { StockMovementDetail } from '@/types/inventory';
import { HttpError, InventoryModuleApi } from '@/sdk';

import { mapStockMovementDetail } from './mappers';

interface UseMovementOptions {
    enabled?: boolean;
}

export function useMovement(
    movementId?: string | number,
    options: UseMovementOptions = {},
): UseQueryResult<StockMovementDetail, HttpError | Error> {
    const inventoryApi = useSdkClient(InventoryModuleApi);
    const enabled = (options.enabled ?? true) && Boolean(movementId);

    return useQuery<Record<string, unknown>, HttpError | Error, StockMovementDetail>({
        queryKey: queryKeys.inventory.movement(movementId ? String(movementId) : 'new'),
        enabled,
        queryFn: async () => {
            if (!movementId) {
                throw new Error('movementId is required');
            }
            return (await inventoryApi.showMovement(movementId)) as Record<string, unknown>;
        },
        select: (response) => mapStockMovementDetail(response),
        staleTime: 15_000,
    });
}
