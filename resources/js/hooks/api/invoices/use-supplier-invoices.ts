import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceSummary } from '@/types/sourcing';
import { mapInvoiceSummary } from './utils';

interface SupplierInvoiceListResponse {
    items?: Record<string, unknown>[];
    meta?: Record<string, unknown> | null;
}

export interface SupplierInvoiceListResult {
    items: InvoiceSummary[];
    meta?: Record<string, unknown> | null;
}

export interface UseSupplierInvoicesParams {
    cursor?: string | null;
    perPage?: number;
    status?: string | 'all';
    poNumber?: string;
    search?: string;
}

const DEFAULT_PER_PAGE = 25;

export function useSupplierInvoices(
    params: UseSupplierInvoicesParams = {},
): UseQueryResult<SupplierInvoiceListResult, ApiError> {
    const {
        cursor,
        perPage = DEFAULT_PER_PAGE,
        status = 'all',
        poNumber,
        search,
    } = params;

    return useQuery<
        SupplierInvoiceListResponse,
        ApiError,
        SupplierInvoiceListResult
    >({
        queryKey: queryKeys.invoices.supplierList({
            cursor: cursor ?? null,
            perPage,
            status,
            poNumber: poNumber?.trim() || undefined,
            search: search?.trim() || undefined,
        }),
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const queryString = buildQuery({
                cursor: cursor ?? undefined,
                per_page: perPage,
                status: status === 'all' ? undefined : status,
                po_number: poNumber?.trim() || undefined,
                search: search?.trim() || undefined,
            });

            const response = await api.get<SupplierInvoiceListResponse>(
                `supplier/invoices${queryString}`,
            );

            return response as unknown as SupplierInvoiceListResponse;
        },
        select: (response) => {
            const rawItems = Array.isArray(response.items)
                ? response.items
                : [];
            const items = rawItems.map((item) => mapInvoiceSummary(item));

            return {
                items,
                meta: response.meta ?? null,
            } satisfies SupplierInvoiceListResult;
        },
        staleTime: 20_000,
        gcTime: 60_000,
    });
}
