import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { PurchaseOrdersApi } from '@/sdk';

export interface CancelPoInput {
    poId: number;
    rfqId?: number | null;
}

export function useCancelPo(): UseMutationResult<void, Error, CancelPoInput> {
    const purchaseOrdersApi = useSdkClient(PurchaseOrdersApi);
    const queryClient = useQueryClient();

    return useMutation<void, Error, CancelPoInput>({
        mutationFn: async ({ poId }) => {
            if (!Number.isFinite(poId) || poId <= 0) {
                throw new Error('A valid PO id is required to cancel.');
            }

            await purchaseOrdersApi.cancelPurchaseOrder({
                purchaseOrderId: poId,
            });
        },
        onSuccess: (_, { poId, rfqId }) => {
            publishToast({
                variant: 'success',
                title: 'Purchase order cancelled',
                description: 'Suppliers will be notified of the cancellation.',
            });

            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.detail(poId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.root(),
            });

            if (rfqId && Number.isFinite(rfqId) && rfqId > 0) {
                const normalizedRfqId = Number(rfqId);
                const relatedKeys = [
                    queryKeys.awards.candidates(normalizedRfqId),
                    queryKeys.awards.summary(normalizedRfqId),
                    queryKeys.rfqs.detail(normalizedRfqId),
                    queryKeys.rfqs.lines(normalizedRfqId),
                    queryKeys.rfqs.quotes(normalizedRfqId),
                    queryKeys.quotes.rfq(normalizedRfqId),
                    queryKeys.quotes.root(),
                ];

                relatedKeys.forEach((key) => {
                    void queryClient.invalidateQueries({ queryKey: key });
                });
            }
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to cancel purchase order',
                description:
                    error.message ?? 'Please try again in a few moments.',
            });
        },
    });
}
