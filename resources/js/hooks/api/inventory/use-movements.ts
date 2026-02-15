import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, InventoryModuleApi, type MovementType } from '@/sdk';
import type { StockMovementSummary } from '@/types/inventory';

import { mapStockMovementSummary } from './mappers';

interface MovementCollectionResponse {
    items?: unknown[];
    data?: unknown;
    meta?: Record<string, unknown> | null;
}

export interface UseMovementsParams {
    cursor?: string | null;
    perPage?: number;
    type?: MovementType | MovementType[];
    itemId?: string;
    locationId?: string;
    dateFrom?: string;
    dateTo?: string;
}

export interface UseMovementsResult {
    items: StockMovementSummary[];
    meta?: Record<string, unknown> | null;
}

export function useMovements(
    params: UseMovementsParams = {},
): UseQueryResult<UseMovementsResult, HttpError | Error> {
    const inventoryApi = useSdkClient(InventoryModuleApi);

    return useQuery<
        MovementCollectionResponse,
        HttpError | Error,
        UseMovementsResult
    >({
        queryKey: queryKeys.inventory.movementsList({
            cursor: params.cursor,
            perPage: params.perPage,
            type: params.type,
            itemId: params.itemId,
            locationId: params.locationId,
            dateFrom: params.dateFrom,
            dateTo: params.dateTo,
        }),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
        queryFn: async () =>
            (await inventoryApi.listMovements({
                cursor: params.cursor,
                perPage: params.perPage,
                type: params.type,
                itemId: params.itemId,
                locationId: params.locationId,
                dateFrom: params.dateFrom,
                dateTo: params.dateTo,
            })) as MovementCollectionResponse,
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
                    .map((entry) => mapStockMovementSummary(entry)),
                meta: response.meta ?? null,
            };
        },
    });
}
