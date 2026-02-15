import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceSummary } from '@/types/sourcing';
import { mapInvoiceSummary } from './utils';

interface InvoiceListResponse {
    items: Record<string, unknown>[];
    meta: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
}

export interface UseInvoicesParams {
    page?: number;
    perPage?: number;
    status?: string | 'all';
    supplierId?: number;
    issuedFrom?: string;
    issuedTo?: string;
}

export interface UseInvoicesResult {
    items: InvoiceSummary[];
    meta: InvoiceListResponse['meta'];
}

export function useInvoices(
    params: UseInvoicesParams = {},
): UseQueryResult<UseInvoicesResult, ApiError> {
    const {
        page = 1,
        perPage = 20,
        status = 'all',
        supplierId,
        issuedFrom,
        issuedTo,
    } = params;

    const query = useQuery<InvoiceListResponse, ApiError, UseInvoicesResult>({
        queryKey: queryKeys.invoices.list({
            page,
            perPage,
            status,
            supplierId,
            issuedFrom,
            issuedTo,
        }),
        queryFn: async () => {
            const requestParams = {
                page,
                per_page: perPage,
                status: status === 'all' ? undefined : status,
                supplier_id: supplierId,
                from: issuedFrom,
                to: issuedTo,
            } satisfies Record<string, unknown>;

            const queryString = buildQuery(requestParams);
            const response = (await api.get<InvoiceListResponse>(
                `/invoices${queryString}`,
            )) as unknown as InvoiceListResponse;

            return response;
        },
        select: (response) => ({
            items: response.items.map((item) => mapInvoiceSummary(item)),
            meta: response.meta,
        }),
        placeholderData: keepPreviousData,
        staleTime: 30_000,
    });

    return query;
}
