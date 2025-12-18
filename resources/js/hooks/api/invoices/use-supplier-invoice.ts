import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceDetail } from '@/types/sourcing';
import { mapInvoiceDetail } from './utils';

export function useSupplierInvoice(invoiceId?: string | number): UseQueryResult<InvoiceDetail, ApiError> {
    const id = invoiceId ? String(invoiceId) : '';
    const enabled = id.length > 0;

    return useQuery<Record<string, unknown>, ApiError, InvoiceDetail>({
        queryKey: queryKeys.invoices.supplierDetail(id),
        enabled,
        queryFn: async () => {
            const response = await api.get<Record<string, unknown>>(`supplier/invoices/${id}`);
            return response as unknown as Record<string, unknown>;
        },
        select: (payload) => mapInvoiceDetail(payload),
        staleTime: 20_000,
    });
}
