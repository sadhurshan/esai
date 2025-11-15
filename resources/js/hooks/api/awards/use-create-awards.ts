import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi } from '@/sdk';
import type { RfqItemAwardSummary } from '@/sdk';

export interface AwardSelectionInput {
    rfqId: number;
    items: Array<{
        rfqItemId: number;
        quoteItemId: number;
        awardedQty?: number;
    }>;
}

export function useCreateAwards(): UseMutationResult<RfqItemAwardSummary[], unknown, AwardSelectionInput> {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation<RfqItemAwardSummary[], unknown, AwardSelectionInput>({
        mutationFn: async ({ rfqId, items }) => {
            if (!items.length) {
                throw new Error('Select at least one RFQ line to award.');
            }

            const response = await rfqsApi.createAwards({
                createAwardsRequest: {
                    rfqId,
                    items,
                },
            });

            return response.data.awards;
        },
        onSuccess: (_awards, { rfqId }) => {
            publishToast({
                variant: 'success',
                title: 'Awards saved',
                description: 'Selections persisted for this RFQ.',
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.awards.candidates(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.awards.summary(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.lines(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.quotes(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.quotes.rfq(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.quotes.root() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.root() });
        },
    });
}
