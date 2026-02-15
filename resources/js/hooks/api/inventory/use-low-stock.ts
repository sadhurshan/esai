import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, InventoryModuleApi } from '@/sdk';
import type { LowStockAlertRow } from '@/types/inventory';

import { mapLowStockAlert } from './mappers';

interface LowStockResponse {
    items?: unknown[];
    data?: unknown;
    meta?: Record<string, unknown> | null;
}

export interface UseLowStockParams {
    cursor?: string | null;
    perPage?: number;
    locationId?: string;
    siteId?: string;
    category?: string;
}

export interface UseLowStockResult {
    items: LowStockAlertRow[];
    meta?: Record<string, unknown> | null;
}

export function useLowStock(
    params: UseLowStockParams = {},
): UseQueryResult<UseLowStockResult, HttpError | Error> {
    const inventoryApi = useSdkClient(InventoryModuleApi);

    return useQuery<LowStockResponse, HttpError | Error, UseLowStockResult>({
        queryKey: queryKeys.inventory.lowStock({
            cursor: params.cursor,
            perPage: params.perPage,
            locationId: params.locationId,
            siteId: params.siteId,
            category: params.category,
        }),
        placeholderData: keepPreviousData,
        queryFn: async () =>
            (await inventoryApi.listLowStock({
                cursor: params.cursor,
                perPage: params.perPage,
                locationId: params.locationId,
                siteId: params.siteId,
                category: params.category,
            })) as LowStockResponse,
        select: (response) => {
            const rawItems = Array.isArray(response.items)
                ? response.items
                : Array.isArray(response.data)
                  ? (response.data as unknown[])
                  : Array.isArray(
                          (response.data as Record<string, unknown> | undefined)
                              ?.items,
                      )
                    ? (((response.data as Record<string, unknown>)
                          .items as unknown[]) ?? [])
                    : [];

            return {
                items: rawItems
                    .filter(
                        (entry): entry is Record<string, unknown> =>
                            typeof entry === 'object' && entry !== null,
                    )
                    .map((entry) => mapLowStockAlert(entry)),
                meta: response.meta ?? null,
            };
        },
        staleTime: 30_000,
    });
}
