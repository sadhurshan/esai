import type { QueryClient } from '@tanstack/react-query';

import { queryKeys } from '@/lib/queryKeys';

export interface InvalidateQuoteOptions {
    quoteId?: string | number;
    rfqId?: string | number;
    invalidateSupplierLists?: boolean;
}

export function invalidateQuoteQueries(queryClient: QueryClient, options: InvalidateQuoteOptions = {}): void {
    const { quoteId, rfqId, invalidateSupplierLists } = options;

    if (quoteId) {
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.detail(quoteId) });
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.lines(quoteId) });
    }

    if (rfqId) {
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.rfq(rfqId) });
        queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.quotes(rfqId) });
        queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });

        if (quoteId) {
            queryClient.invalidateQueries({ queryKey: queryKeys.quotes.revisions(rfqId, quoteId) });
        }
    }

    if (invalidateSupplierLists) {
        queryClient.invalidateQueries({ queryKey: queryKeys.quotes.supplierRoot() });
    }
}
