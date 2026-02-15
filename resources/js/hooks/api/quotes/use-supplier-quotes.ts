import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type {
    HttpError,
    ListSupplierQuotes200Response,
    ListSupplierQuotes200ResponseAllOfDataMeta,
    Quote,
    QuoteStatusEnum,
} from '@/sdk';
import { QuotesApi } from '@/sdk';

export type SupplierQuoteSort = 'created_at' | 'submitted_at' | 'total_minor';

export interface UseSupplierQuotesFilters {
    rfqId?: string | number;
    rfqNumber?: string;
    status?: QuoteStatusEnum;
    page?: number;
    perPage?: number;
    sort?: SupplierQuoteSort;
}

export interface SupplierQuoteListResult {
    items: Quote[];
    meta?: ListSupplierQuotes200ResponseAllOfDataMeta;
    total: number;
    page?: number;
    perPage?: number;
}

export function useSupplierQuotes(
    filters: UseSupplierQuotesFilters = {},
    options?: { enabled?: boolean },
): UseQueryResult<SupplierQuoteListResult, HttpError> {
    const quotesApi = useSdkClient(QuotesApi);
    const { rfqId, rfqNumber, status, page, perPage, sort } = filters;

    const normalizedFilters = useMemo(() => {
        return {
            rfqId: rfqId != null ? String(rfqId) : undefined,
            rfqNumber: rfqNumber?.trim() || undefined,
            status,
            page,
            perPage,
            sort,
        } satisfies UseSupplierQuotesFilters;
    }, [page, perPage, rfqId, rfqNumber, sort, status]);

    return useQuery<
        ListSupplierQuotes200Response,
        HttpError,
        SupplierQuoteListResult
    >({
        queryKey: queryKeys.quotes.supplierList(normalizedFilters),
        enabled: options?.enabled ?? true,
        placeholderData: keepPreviousData,
        queryFn: async () => {
            return quotesApi.listSupplierQuotes({
                rfqId: normalizedFilters.rfqId,
                rfqNumber: normalizedFilters.rfqNumber,
                status: normalizedFilters.status,
                page: normalizedFilters.page,
                perPage: normalizedFilters.perPage,
                sort: normalizedFilters.sort,
            });
        },
        select: (response) => {
            const payload = response.data;
            const items = payload?.items ?? [];
            const meta = payload?.meta;

            return {
                items,
                meta,
                total: meta?.total ?? items.length,
                page: meta?.currentPage ?? normalizedFilters.page,
                perPage: meta?.perPage ?? normalizedFilters.perPage,
            };
        },
        staleTime: 15_000,
    });
}
