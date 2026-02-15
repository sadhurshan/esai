import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { RfqItemAwardSummary } from '@/sdk';
import { RFQsApi } from '@/sdk';

export interface DeleteAwardInput {
    rfqId: number;
    awardId: number;
}

export function useDeleteAward(): UseMutationResult<
    RfqItemAwardSummary[],
    unknown,
    DeleteAwardInput
> {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation<RfqItemAwardSummary[], unknown, DeleteAwardInput>({
        mutationFn: async ({ awardId }) => {
            if (!Number.isFinite(awardId) || awardId <= 0) {
                throw new Error(
                    'A valid award id is required to delete an award.',
                );
            }

            const response = await rfqsApi.deleteAward({
                awardId,
            });

            return response.data.awards;
        },
        onSuccess: (_awards, { rfqId }) => {
            publishToast({
                variant: 'success',
                title: 'Award removed',
                description: 'The RFQ line is open for re-assignment.',
            });

            void queryClient.invalidateQueries({
                queryKey: queryKeys.awards.candidates(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.awards.summary(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.detail(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.lines(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.quotes(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.quotes.rfq(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.quotes.root(),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.root(),
            });
        },
    });
}
