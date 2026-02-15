import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, ReceivingApi } from '@/sdk';
import type { GoodsReceiptNoteSummary } from '@/types/sourcing';

import { mapGrnSummary } from './utils';

interface GrnCollectionResponse {
    items?: Record<string, unknown>[];
    data?: Record<string, unknown>[];
    meta?: Record<string, unknown> | null;
}

export interface GrnListResult {
    items: GoodsReceiptNoteSummary[];
    meta?: Record<string, unknown> | null;
}

export interface UseGrnsParams {
    cursor?: string | null;
    perPage?: number;
    purchaseOrderId?: number;
    supplierId?: number;
    status?: string;
    search?: string;
    receivedFrom?: string;
    receivedTo?: string;
}

const DEFAULT_PER_PAGE = 25;

export function useGrns(
    params: UseGrnsParams = {},
    options: { enabled?: boolean } = {},
): UseQueryResult<GrnListResult, HttpError> {
    const receivingApi = useSdkClient(ReceivingApi);
    const enabled = options.enabled ?? true;

    return useQuery<GrnCollectionResponse, HttpError, GrnListResult>({
        queryKey: queryKeys.receiving.list({
            cursor: params.cursor,
            perPage: params.perPage ?? DEFAULT_PER_PAGE,
            purchaseOrderId: params.purchaseOrderId,
            supplierId: params.supplierId,
            status: params.status,
            search: params.search,
            receivedFrom: params.receivedFrom,
            receivedTo: params.receivedTo,
        }),
        enabled,
        placeholderData: keepPreviousData,
        queryFn: async () =>
            (await receivingApi.listGrns({
                cursor: params.cursor,
                perPage: params.perPage ?? DEFAULT_PER_PAGE,
                purchaseOrderId: params.purchaseOrderId,
                supplierId: params.supplierId,
                status: params.status,
                search: params.search,
                receivedFrom: params.receivedFrom,
                receivedTo: params.receivedTo,
            })) as GrnCollectionResponse,
        select: (response) => {
            const rawItems = (response?.items ??
                response?.data ??
                []) as unknown[];
            const items = rawItems
                .filter(
                    (item): item is Record<string, unknown> =>
                        typeof item === 'object' && item !== null,
                )
                .map((item) => mapGrnSummary(item));

            return {
                items,
                meta: response?.meta ?? null,
            };
        },
        staleTime: 15_000,
        gcTime: 60_000,
    });
}
