import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { InventoryItemSummary } from '@/types/inventory';
import { HttpError, InventoryModuleApi } from '@/sdk';

import { mapInventoryItemSummary } from './mappers';

interface InventoryCollectionResponse {
    items?: unknown[];
    data?: unknown;
    meta?: Record<string, unknown> | null;
}

export interface UseItemsParams {
    cursor?: string | null;
    perPage?: number;
    sku?: string;
    name?: string;
    category?: string;
    status?: 'active' | 'inactive';
    siteId?: string;
    belowMin?: boolean;
}

export interface UseItemsResult {
    items: InventoryItemSummary[];
    meta?: Record<string, unknown> | null;
}

export function useItems(params: UseItemsParams = {}): UseQueryResult<UseItemsResult, HttpError | Error> {
    const inventoryApi = useSdkClient(InventoryModuleApi);

    return useQuery<InventoryCollectionResponse, HttpError | Error, UseItemsResult>({
        queryKey: queryKeys.inventory.items({
            cursor: params.cursor,
            perPage: params.perPage,
            sku: params.sku,
            name: params.name,
            category: params.category,
            status: params.status,
            siteId: params.siteId,
            belowMin: params.belowMin,
        }),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
        queryFn: async () =>
            (await inventoryApi.listItems({
                cursor: params.cursor,
                perPage: params.perPage,
                sku: params.sku,
                name: params.name,
                category: params.category,
                status: params.status,
                siteId: params.siteId,
                belowMin: params.belowMin,
            })) as InventoryCollectionResponse,
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
                    .map((entry) => mapInventoryItemSummary(entry)),
                meta: response.meta ?? null,
            };
        },
    });
}
