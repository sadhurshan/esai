import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderDetail } from '@/types/sourcing';
import {
    mapChangeOrder,
    mapPurchaseOrder,
    mapPurchaseOrderLine,
    type PurchaseOrderResponse,
} from './usePurchaseOrder';

interface AwardQuoteInput {
    quoteId: number;
}

export function useAwardQuote(
    rfqId: number,
): UseMutationResult<PurchaseOrderDetail, ApiError, AwardQuoteInput> {
    const queryClient = useQueryClient();

    return useMutation<PurchaseOrderDetail, ApiError, AwardQuoteInput>({
        mutationFn: async ({ quoteId }) => {
            const response = (await api.post<PurchaseOrderResponse>(
                `/rfqs/${rfqId}/award`,
                {
                    quote_id: quoteId,
                },
            )) as unknown as PurchaseOrderResponse;

            return {
                ...mapPurchaseOrder(response),
                lines: (response.lines ?? []).map(mapPurchaseOrderLine),
                changeOrders: (response.change_orders ?? []).map(mapChangeOrder),
            };
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.quotes(rfqId) });
            queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.root() });
        },
    });
}
