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
    CreateWebhookPayload,
    WebhookSubscriptionItem,
} from '@/types/admin';

export function useCreateWebhook(): UseMutationResult<
    WebhookSubscriptionItem,
    unknown,
    CreateWebhookPayload
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<WebhookSubscriptionItem, unknown, CreateWebhookPayload>({
        mutationFn: async (payload) => adminConsoleApi.createWebhook(payload),
        onSuccess: () => {
            successToast('Webhook created', 'Subscription saved successfully.');
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.webhooks(),
            });
        },
        onError: (error) => {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to create webhook.';
            errorToast('Webhook creation failed', message);
        },
    });
}
