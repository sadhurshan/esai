import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { HttpError, ListQuotesForRfq200Response, Quote, QuoteStatusEnum, RequestMeta } from '@/sdk';
import { QuotesApi } from '@/sdk';

export type QuoteListSort = 'submitted_at' | 'lead_time_days' | 'total_minor';

export interface RangeFilter {
    min?: number | null;
    max?: number | null;
}

export interface UseQuotesFilters {
    supplierId?: string | number;
    status?: QuoteStatusEnum | QuoteStatusEnum[];
    statuses?: QuoteStatusEnum | QuoteStatusEnum[];
    priceRangeMinor?: RangeFilter;
    leadTimeRangeDays?: RangeFilter;
    page?: number;
    perPage?: number;
    sort?: QuoteListSort;
}

export interface UseQuotesResult {
    items: Quote[];
    meta?: RequestMeta;
    total: number;
    page?: number;
    perPage?: number;
    isClientSideFiltered: boolean;
}

interface NormalizedQuoteFilters {
    supplierId?: string;
    statuses: QuoteStatusEnum[];
    priceRangeMinor?: RangeFilter;
    leadTimeRangeDays?: RangeFilter;
    page?: number;
    perPage?: number;
    sort?: QuoteListSort;
    hasClientFilters: boolean;
}

export function useQuotes(
    rfqId?: string | number,
    filters: UseQuotesFilters = {},
    options?: { enabled?: boolean },
): UseQueryResult<UseQuotesResult, HttpError> {
    const quotesApi = useSdkClient(QuotesApi);
    const filtersSignature = JSON.stringify(filters ?? {});
    const normalizedFilters = useMemo(() => normalizeFilters(filters), [filters]);
    const queryKeyFilters = useMemo(() => ({ signature: filtersSignature }), [filtersSignature]);

    return useQuery<ListQuotesForRfq200Response, HttpError, UseQuotesResult>({
        queryKey: queryKeys.quotes.list(String(rfqId ?? ''), queryKeyFilters),
        enabled: Boolean(rfqId) && (options?.enabled ?? true),
        placeholderData: keepPreviousData,
        queryFn: async () => {
            if (!rfqId) {
                throw new Error('rfqId is required to list quotes');
            }

            return quotesApi.listQuotesForRfq({ rfqId: String(rfqId) });
        },
        select: (response) => {
            const serverItems = response.data?.items ?? [];
            const filteredItems = normalizedFilters.hasClientFilters
                ? applyClientFilters(serverItems, normalizedFilters)
                : serverItems;
            const pagedItems = applyClientSidePagination(filteredItems, normalizedFilters);
            const pagination = response.meta?.pagination;

            const total = normalizedFilters.hasClientFilters
                ? filteredItems.length
                : pagination?.total ?? filteredItems.length;

            return {
                items: pagedItems,
                meta: response.meta,
                total,
                page: normalizedFilters.page ?? pagination?.currentPage,
                perPage: normalizedFilters.perPage ?? pagination?.perPage ?? pagedItems.length,
                isClientSideFiltered:
                    normalizedFilters.hasClientFilters || Boolean(normalizedFilters.page || normalizedFilters.perPage),
            };
        },
        staleTime: 30_000,
    });
}

function normalizeFilters(filters: UseQuotesFilters): NormalizedQuoteFilters {
    const statuses = toArray(filters.statuses ?? filters.status).filter(Boolean) as QuoteStatusEnum[];
    const supplierId = filters.supplierId != null ? String(filters.supplierId) : undefined;
    const priceRangeMinor = sanitizeRange(filters.priceRangeMinor);
    const leadTimeRangeDays = sanitizeRange(filters.leadTimeRangeDays);
    const page = normalizePositiveInteger(filters.page);
    const perPage = normalizePositiveInteger(filters.perPage);

    return {
        supplierId,
        statuses,
        priceRangeMinor,
        leadTimeRangeDays,
        page,
        perPage,
        sort: filters.sort,
        hasClientFilters: Boolean(supplierId || statuses.length || priceRangeMinor || leadTimeRangeDays),
    };
}

function sanitizeRange(range?: RangeFilter): RangeFilter | undefined {
    if (!range) {
        return undefined;
    }

    const min = typeof range.min === 'number' ? range.min : undefined;
    const max = typeof range.max === 'number' ? range.max : undefined;

    if (min == null && max == null) {
        return undefined;
    }

    return { min, max };
}

function normalizePositiveInteger(value?: number): number | undefined {
    if (typeof value !== 'number') {
        return undefined;
    }

    return value > 0 ? Math.floor(value) : undefined;
}

function toArray<T>(value?: T | T[]): T[] {
    if (value == null) {
        return [];
    }

    return Array.isArray(value) ? value : [value];
}

function applyClientFilters(quotes: Quote[], filters: NormalizedQuoteFilters): Quote[] {
    // TODO: replace with server-side filtering once the Quotes API accepts these filter parameters per spec.
    return quotes.filter((quote) => {
        if (filters.supplierId && String(quote.supplierId) !== filters.supplierId) {
            return false;
        }

        if (filters.statuses.length && !filters.statuses.includes(quote.status)) {
            return false;
        }

        if (!matchesRange(quote.totalMinor, filters.priceRangeMinor)) {
            return false;
        }

        if (!matchesRange(quote.leadTimeDays ?? undefined, filters.leadTimeRangeDays)) {
            return false;
        }

        return true;
    });
}

function matchesRange(value: number | undefined, range?: RangeFilter): boolean {
    if (!range) {
        return true;
    }

    if (typeof value !== 'number') {
        return false;
    }

    if (typeof range.min === 'number' && value < range.min) {
        return false;
    }

    if (typeof range.max === 'number' && value > range.max) {
        return false;
    }

    return true;
}

function applyClientSidePagination(quotes: Quote[], filters: NormalizedQuoteFilters): Quote[] {
    if (!filters.page || !filters.perPage) {
        return quotes;
    }

    // TODO: replace with server-side pagination parameters once exposed by the Quotes API.
    const start = (filters.page - 1) * filters.perPage;
    return quotes.slice(start, start + filters.perPage);
}
