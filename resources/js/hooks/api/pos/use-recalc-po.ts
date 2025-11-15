import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { MoneyApi } from '@/sdk';

export interface RecalcPoInput {
    poId: number;
}

export function useRecalcPo(): UseMutationResult<void, unknown, RecalcPoInput> {
    const moneyApi = useSdkClient(MoneyApi);
    const queryClient = useQueryClient();

    return useMutation<void, unknown, RecalcPoInput>({
        mutationFn: async ({ poId }) => {
            if (!Number.isFinite(poId) || poId <= 0) {
                throw new Error('A valid PO id is required to recalculate totals.');
            }

            await moneyApi.recalcPurchaseOrderTotals({
                purchaseOrderId: String(poId),
            });
        },
        onSuccess: (_, { poId }) => {
            publishToast({
                variant: 'success',
                title: 'Totals recalculated',
                description: 'Latest pricing and taxes applied to this PO.',
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.detail(poId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.root() });
        },
    });
}
