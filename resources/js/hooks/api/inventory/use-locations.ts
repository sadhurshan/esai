import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { InventoryLocationOption } from '@/types/inventory';
import { HttpError, InventoryModuleApi } from '@/sdk';

import { mapInventoryLocationOption } from './mappers';

interface LocationResponse {
    items?: unknown[];
    data?: unknown;
    meta?: Record<string, unknown> | null;
}

export interface UseLocationsParams {
    cursor?: string | null;
    perPage?: number;
    siteId?: string;
    type?: 'site' | 'bin' | 'zone';
    search?: string;
    enabled?: boolean;
}

export interface UseLocationsResult {
    items: InventoryLocationOption[];
    meta?: Record<string, unknown> | null;
}

export function useLocations(
    params: UseLocationsParams = {},
): UseQueryResult<UseLocationsResult, HttpError | Error> {
    const inventoryApi = useSdkClient(InventoryModuleApi);
    const enabled = params.enabled ?? true;

    return useQuery<LocationResponse, HttpError | Error, UseLocationsResult>({
        queryKey: queryKeys.inventory.locations({
            cursor: params.cursor,
            perPage: params.perPage,
            siteId: params.siteId,
            type: params.type,
            search: params.search,
        }),
        enabled,
        placeholderData: keepPreviousData,
        queryFn: async () =>
            (await inventoryApi.listLocations({
                cursor: params.cursor,
                perPage: params.perPage,
                siteId: params.siteId,
                type: params.type,
                search: params.search,
            })) as LocationResponse,
        select: (response) => {
            const rawItems = Array.isArray(response.items)
                ? response.items
                : Array.isArray(response.data)
                  ? (response.data as unknown[])
                  : Array.isArray((response.data as Record<string, unknown> | undefined)?.items)
                    ? (((response.data as Record<string, unknown>).items as unknown[]) ?? [])
                    : [];

            return {
                items: rawItems
                    .filter((entry): entry is Record<string, unknown> => typeof entry === 'object' && entry !== null)
                    .map((entry) => mapInventoryLocationOption(entry)),
                meta: response.meta ?? null,
            };
        },
        staleTime: 60_000,
    });
}
