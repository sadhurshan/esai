import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { AdminConsoleApi } from '@/sdk';

export interface RetryWebhookDeliveryInput {
    deliveryId: string;
    subscriptionId?: string;
}

export function useRetryWebhookDelivery(): UseMutationResult<void, unknown, RetryWebhookDeliveryInput> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<void, unknown, RetryWebhookDeliveryInput>({
        mutationFn: async ({ deliveryId }) => adminConsoleApi.retryWebhookDelivery(deliveryId),
        onSuccess: (_data, variables) => {
            successToast('Delivery retried', 'Webhook delivery queued again.');
            if (variables.subscriptionId) {
                queryClient.invalidateQueries({
                    queryKey: ['admin', 'webhooks', variables.subscriptionId, 'deliveries'],
                    exact: false,
                });
            }
        },
        onError: (error) => {
            const message = error instanceof Error ? error.message : 'Unable to retry delivery.';
            errorToast('Retry failed', message);
        },
    });
}
