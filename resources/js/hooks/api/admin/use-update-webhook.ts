import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { errorToast, successToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type {
    UpdateWebhookPayload,
    WebhookSubscriptionItem,
} from '@/types/admin';

export interface UpdateWebhookInput extends UpdateWebhookPayload {
    subscriptionId: string;
}

export function useUpdateWebhook(): UseMutationResult<
    WebhookSubscriptionItem,
    unknown,
    UpdateWebhookInput
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<WebhookSubscriptionItem, unknown, UpdateWebhookInput>({
        mutationFn: async ({ subscriptionId, ...updates }) =>
            adminConsoleApi.updateWebhook(subscriptionId, updates),
        onSuccess: (_data, variables) => {
            successToast('Webhook updated', 'Subscription changes saved.');
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.webhooks(),
            });
            queryClient.invalidateQueries({
                queryKey: [
                    'admin',
                    'webhooks',
                    variables.subscriptionId,
                    'deliveries',
                ],
                exact: false,
            });
        },
        onError: (error) => {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to update webhook.';
            errorToast('Webhook update failed', message);
        },
    });
}
