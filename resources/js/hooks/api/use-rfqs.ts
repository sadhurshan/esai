import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import {
    ListRfqsSortDirectionEnum,
    ListRfqsSortEnum,
    ListRfqsTabEnum,
    RfqStatusEnum,
    type PageMeta,
    type Rfq,
    type RfqCollection,
    RFQsApi,
} from '@/sdk';

export type RfqStatusFilter = 'all' | 'draft' | 'open' | 'closed' | 'awarded';

export interface UseRfqsParams {
    page?: number;
    perPage?: number;
    status?: RfqStatusFilter;
    search?: string;
    dateFrom?: string;
    dateTo?: string;
}

export type UseRfqsResult = UseQueryResult<RfqCollection, unknown> & {
    items: Rfq[];
    meta?: PageMeta;
    total: number;
    isClientSideFiltered: boolean;
};

const STATUS_MAP: Record<Exclude<RfqStatusFilter, 'all'>, RfqStatusEnum> = {
    draft: RfqStatusEnum.Awaiting,
    open: RfqStatusEnum.Open,
    closed: RfqStatusEnum.Closed,
    awarded: RfqStatusEnum.Awarded,
};

function resolveStatus(status: RfqStatusFilter): RfqStatusEnum | undefined {
    if (status === 'all') {
        return undefined;
    }

    return STATUS_MAP[status];
}

function resolveTab(status: RfqStatusFilter): ListRfqsTabEnum {
    if (status === 'open') {
        return ListRfqsTabEnum.Open;
    }

    // TODO: clarify how API tabs should map to remaining status filters once backend exposes dedicated segments.
    return ListRfqsTabEnum.All;
}

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

export function useRfqs(params: UseRfqsParams = {}): UseRfqsResult {
    const rfqsApi = useSdkClient(RFQsApi);
    const { page = 1, perPage = 10, status = 'all', search, dateFrom, dateTo } = params;
    const tab = resolveTab(status);
    const sanitizedSearch = search?.trim();
    const fromTimestamp = toStartOfDayTimestamp(dateFrom);
    const toTimestamp = toEndOfDayTimestamp(dateTo);
    const statusFilter = resolveStatus(status);

    const query = useQuery<RfqCollection>({
        queryKey: ['rfqs', { page, perPage, status, search, dateFrom, dateTo }],
        queryFn: async () => {
            const response = await rfqsApi.listRfqs({
                perPage,
                page,
                tab: tab === ListRfqsTabEnum.All ? undefined : tab,
                q: sanitizedSearch && sanitizedSearch.length > 0 ? sanitizedSearch : undefined,
                sort: ListRfqsSortEnum.DeadlineAt,
                sortDirection: ListRfqsSortDirectionEnum.Asc,
            });

            return response.data;
        },
        placeholderData: keepPreviousData,
    });

    const shouldFilterByStatusClientSide = Boolean(statusFilter && tab === ListRfqsTabEnum.All);
    const shouldFilterByDate = fromTimestamp != null || toTimestamp != null;

    const items = useMemo<Rfq[]>(() => {
        const sourceItems = query.data?.items ?? [];

        if (!shouldFilterByStatusClientSide && !shouldFilterByDate) {
            return sourceItems;
        }

        return sourceItems.filter((item) => {
            if (shouldFilterByStatusClientSide && statusFilter && item.status !== statusFilter) {
                return false;
            }

            const sentAtTimestamp = item.sentAt?.getTime();

            if (fromTimestamp != null && (sentAtTimestamp == null || sentAtTimestamp < fromTimestamp)) {
                return false;
            }

            if (toTimestamp != null && (sentAtTimestamp == null || sentAtTimestamp > toTimestamp)) {
                return false;
            }

            return true;
        });
    }, [query.data, statusFilter, fromTimestamp, toTimestamp, shouldFilterByStatusClientSide, shouldFilterByDate]);
    const meta = query.data?.meta;
    const isClientSideFiltered = shouldFilterByStatusClientSide || shouldFilterByDate;
    const total = isClientSideFiltered ? items.length : meta?.total ?? items.length;

    return {
        ...query,
        items,
        meta,
        total,
        isClientSideFiltered,
    } satisfies UseRfqsResult;
}
