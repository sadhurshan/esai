import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { InventoryItemDetail } from '@/types/inventory';
import { HttpError, InventoryModuleApi } from '@/sdk';

import { mapInventoryItemDetail } from './mappers';

interface UseItemOptions {
    enabled?: boolean;
}

export function useItem(
    itemId?: string | number,
    options: UseItemOptions = {},
): UseQueryResult<InventoryItemDetail, HttpError | Error> {
    const inventoryApi = useSdkClient(InventoryModuleApi);
    const enabled = (options.enabled ?? true) && Boolean(itemId);

    return useQuery<Record<string, unknown>, HttpError | Error, InventoryItemDetail>({
        queryKey: queryKeys.inventory.item(itemId ? String(itemId) : 'new'),
        enabled,
        staleTime: 30_000,
        queryFn: async () => {
            if (!itemId) {
                throw new Error('itemId is required');
            }
            return (await inventoryApi.showItem(itemId)) as Record<string, unknown>;
        },
        select: (response) => mapInventoryItemDetail(response),
    });
}
