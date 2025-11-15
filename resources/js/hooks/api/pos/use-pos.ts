import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { PurchaseOrdersApi } from '@/sdk';
import type {
    ListPurchaseOrders200ResponseAllOfData,
    ListPurchaseOrdersStatusParameter,
    PageMeta,
} from '@/sdk';
import type { PurchaseOrderSummary } from '@/types/sourcing';
import { mapPurchaseOrder } from '@/hooks/api/usePurchaseOrder';

export interface UsePosParams {
    page?: number;
    perPage?: number;
    status?: ListPurchaseOrdersStatusParameter | 'all';
    supplierId?: number;
    issuedFrom?: string;
    issuedTo?: string;
    ackStatus?: 'all' | 'draft' | 'sent' | 'acknowledged' | 'declined';
}

export type UsePosResult = UseQueryResult<ListPurchaseOrders200ResponseAllOfData, unknown> & {
    items: PurchaseOrderSummary[];
    meta?: PageMeta;
    total: number;
    isClientSideFiltered: boolean;
};

function toStartOfDayTimestamp(value?: string): number | undefined {
    if (!value) {
        return undefined;
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return undefined;
    }

    parsed.setHours(0, 0, 0, 0);
    return parsed.getTime();
}

function toEndOfDayTimestamp(value?: string): number | undefined {
    if (!value) {
        return undefined;
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return undefined;
    }

    parsed.setHours(23, 59, 59, 999);
    return parsed.getTime();
}

export function usePos(params: UsePosParams = {}): UsePosResult {
    const purchaseOrdersApi = useSdkClient(PurchaseOrdersApi);
    const { page = 1, perPage = 20, status = 'all', supplierId, issuedFrom, issuedTo, ackStatus = 'all' } = params;
    const normalizedStatus = status === 'all' ? undefined : status;
    const issuedFromTimestamp = toStartOfDayTimestamp(issuedFrom);
    const issuedToTimestamp = toEndOfDayTimestamp(issuedTo);

    const query = useQuery<ListPurchaseOrders200ResponseAllOfData>({
        queryKey: queryKeys.purchaseOrders.list({ page, perPage, status, supplierId, issuedFrom, issuedTo, ackStatus }),
        queryFn: async () => {
            const response = await purchaseOrdersApi.listPurchaseOrders({
                perPage,
                page,
                status: normalizedStatus,
            });
            return response.data;
        },
        placeholderData: keepPreviousData,
        staleTime: 30_000,
    });

    const rawItems = useMemo(() => query.data?.items ?? [], [query.data?.items]);
    const shouldFilter =
        supplierId != null || issuedFromTimestamp != null || issuedToTimestamp != null || ackStatus !== 'all';

    const filteredItems = useMemo(() => {
        if (!shouldFilter) {
            return rawItems;
        }

        return rawItems.filter((item) => {
            if (supplierId != null && item.supplier?.id !== supplierId) {
                return false;
            }

            const createdAt = item.createdAt?.getTime();
            if (issuedFromTimestamp != null && (createdAt == null || createdAt < issuedFromTimestamp)) {
                return false;
            }

            if (issuedToTimestamp != null && (createdAt == null || createdAt > issuedToTimestamp)) {
                return false;
            }

            if (ackStatus !== 'all') {
                const extra = item as unknown as Record<string, unknown>;
                const normalizedAck = (extra.ackStatus ?? extra.ack_status ?? 'draft') as string;
                if (normalizedAck !== ackStatus) {
                    return false;
                }
            }

            return true;
        });
    }, [ackStatus, issuedFromTimestamp, issuedToTimestamp, rawItems, shouldFilter, supplierId]);

    const items = useMemo(() => filteredItems.map(mapPurchaseOrder), [filteredItems]);
    const meta = query.data?.meta;
    const total = shouldFilter ? items.length : meta?.total ?? items.length;

    return {
        ...query,
        items,
        meta,
        total,
        isClientSideFiltered: shouldFilter,
    } satisfies UsePosResult;
}
