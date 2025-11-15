import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { PurchaseOrdersApi, type ExportPurchaseOrderResponse } from '@/sdk';

export interface ExportPoInput {
    poId: number;
}

export function useExportPo(): UseMutationResult<ExportPurchaseOrderResponse, Error, ExportPoInput> {
    const purchaseOrdersApi = useSdkClient(PurchaseOrdersApi);
    const queryClient = useQueryClient();

    return useMutation<ExportPurchaseOrderResponse, Error, ExportPoInput>({
        mutationFn: async ({ poId }) => {
            if (!Number.isFinite(poId) || poId <= 0) {
                throw new Error('A valid PO id is required to generate a PDF.');
            }

            const response = await purchaseOrdersApi.exportPurchaseOrder({
                purchaseOrderId: poId,
            });

            return response.data;
        },
        onSuccess: (_payload, { poId }) => {
            publishToast({
                variant: 'success',
                title: 'Purchase order PDF ready',
                description: 'Download link generated for this PO.',
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.detail(poId) });
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to export purchase order',
                description: error.message ?? 'Please try again shortly.',
            });
        },
    });
}
