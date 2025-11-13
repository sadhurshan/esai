import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import {
    ListRfqsSortDirectionEnum,
    ListRfqsSortEnum,
    ListRfqsTabEnum,
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

const STATUS_VALUES: Record<RfqStatusFilter, string[]> = {
    all: [],
    draft: ['awaiting'],
    open: ['open'],
    closed: ['closed', 'cancelled'],
    awarded: ['awarded'],
};

function resolveTab(status: RfqStatusFilter): ListRfqsTabEnum | undefined {
    if (status === 'open') {
        return ListRfqsTabEnum.Open;
    }

    return undefined;
}

function matchesStatus(statusFilter: RfqStatusFilter, rfq: Rfq): boolean {
    if (statusFilter === 'all') {
        return true;
    }

    const expected = STATUS_VALUES[statusFilter];
    if (!expected || expected.length === 0) {
        return true;
    }

    return expected.includes(rfq.status ?? '');
}

function matchesDateRange(rfq: Rfq, dateFrom?: string, dateTo?: string): boolean {
    if (!dateFrom && !dateTo) {
        return true;
    }

    // Prefer sentAt (publish date), fall back to createdAt for drafts.
    const referenceDate = rfq.sentAt ?? rfq.createdAt ?? null;
    if (!referenceDate) {
        return false;
    }

    const timestamp = referenceDate.getTime();

    if (dateFrom) {
        const start = new Date(dateFrom);
        if (!Number.isNaN(start.getTime()) && timestamp < start.getTime()) {
            return false;
        }
    }

    if (dateTo) {
        const end = new Date(dateTo);
        if (!Number.isNaN(end.getTime()) && timestamp > end.getTime()) {
            return false;
        }
    }

    return true;
}

function applyLocalFilters(
    items: Rfq[],
    status: RfqStatusFilter,
    dateFrom?: string,
    dateTo?: string,
): Rfq[] {
    return items.filter((item) => matchesStatus(status, item) && matchesDateRange(item, dateFrom, dateTo));
}

function inferClientFiltering(status: RfqStatusFilter, dateFrom?: string, dateTo?: string): boolean {
    if (dateFrom || dateTo) {
        return true;
    }

    return status !== 'all' && status !== 'open';
}

export function useRfqs(params: UseRfqsParams = {}): UseRfqsResult {
    const rfqsApi = useSdkClient(RFQsApi);
    const { page = 1, perPage = 10, status = 'all', search, dateFrom, dateTo } = params;

    const tab = resolveTab(status);

    const query = useQuery<RfqCollection>({
        queryKey: ['rfqs', { page, perPage, status, search, dateFrom, dateTo }],
        queryFn: async () => {
            const response = await rfqsApi.listRfqs({
                perPage,
                page,
                q: search && search.length > 0 ? search : undefined,
                sort: ListRfqsSortEnum.DeadlineAt,
                sortDirection: ListRfqsSortDirectionEnum.Asc,
                ...(tab ? { tab } : {}),
            });

            return response.data;
        },
        placeholderData: keepPreviousData,
    });

    const items = useMemo(() => query.data?.items ?? [], [query.data]);
    const filteredItems = useMemo(() => applyLocalFilters(items, status, dateFrom, dateTo), [items, status, dateFrom, dateTo]);
    const isClientSideFiltered = inferClientFiltering(status, dateFrom, dateTo);
    const meta = query.data?.meta;
    const total = isClientSideFiltered ? filteredItems.length : meta?.total ?? filteredItems.length;

    return {
        ...query,
        items: filteredItems,
        meta,
        total,
        isClientSideFiltered,
    } satisfies UseRfqsResult;
}
