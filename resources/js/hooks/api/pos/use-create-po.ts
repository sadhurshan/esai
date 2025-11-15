import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { PurchaseOrdersApi } from '@/sdk';
import type { PurchaseOrderSummary } from '@/types/sourcing';
import { mapPurchaseOrder } from '@/hooks/api/usePurchaseOrder';

export interface CreatePoInput {
    awardIds: number[];
    rfqId?: number;
}

export function useCreatePo(): UseMutationResult<PurchaseOrderSummary[], unknown, CreatePoInput> {
    const purchaseOrdersApi = useSdkClient(PurchaseOrdersApi);
    const queryClient = useQueryClient();

    return useMutation<PurchaseOrderSummary[], unknown, CreatePoInput>({
        mutationFn: async ({ awardIds }) => {
            if (!awardIds.length) {
                throw new Error('Select at least one awarded line before converting to PO.');
            }

            const response = await purchaseOrdersApi.createPurchaseOrdersFromAwards({
                createPurchaseOrdersFromAwardsRequest: {
                    awardIds,
                },
            });

            const purchaseOrders = response.data.purchaseOrders ?? [];
            return purchaseOrders.map(mapPurchaseOrder);
        },
        onSuccess: (_pos, { rfqId }) => {
            publishToast({
                variant: 'success',
                title: 'Purchase order draft ready',
                description: 'Awarded lines converted to draft purchase order(s).',
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.root() });

            if (rfqId != null) {
                void queryClient.invalidateQueries({ queryKey: queryKeys.awards.candidates(rfqId) });
                void queryClient.invalidateQueries({ queryKey: queryKeys.awards.summary(rfqId) });
                void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
                void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.lines(rfqId) });
                void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.quotes(rfqId) });
                void queryClient.invalidateQueries({ queryKey: queryKeys.quotes.rfq(rfqId) });
                void queryClient.invalidateQueries({ queryKey: queryKeys.quotes.root() });
            }
        },
    });
}
