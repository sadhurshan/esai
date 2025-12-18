import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderDelivery } from '@/types/sourcing';

export interface SendPoInput {
    poId: number;
    message?: string;
    overrideEmail?: string;
}

interface SendPoResponse {
    deliveries?: PurchaseOrderDelivery[];
}

export function useSendPo(): UseMutationResult<PurchaseOrderDelivery | undefined, Error, SendPoInput> {
    const queryClient = useQueryClient();

    return useMutation<PurchaseOrderDelivery | undefined, Error, SendPoInput>({
        mutationFn: async ({ poId, message, overrideEmail }) => {
            if (!Number.isFinite(poId) || poId <= 0) {
                throw new Error('A valid PO id is required to send to the supplier.');
            }

            // TODO: switch to the TS SDK once the send endpoint accepts payloads in the generated client.
            const response = (await api.post<SendPoResponse>(`/purchase-orders/${poId}/send`, {
                message: message?.trim() || undefined,
                override_email: overrideEmail?.trim() || undefined,
            })) as unknown as SendPoResponse;

            return response?.deliveries?.[0];
        },
        onSuccess: (_, { poId }) => {
            publishToast({
                variant: 'success',
                title: 'Purchase order sent',
                description: 'Supplier has been notified via email and webhook.',
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.detail(poId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.root() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.events(poId) });
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to send purchase order',
                description: error.message ?? 'Please try again in a few moments.',
            });
        },
    });
}
