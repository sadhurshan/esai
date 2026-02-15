import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { errorToast, successToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';

export interface DeleteWebhookInput {
    subscriptionId: string;
}

export function useDeleteWebhook(): UseMutationResult<
    void,
    unknown,
    DeleteWebhookInput
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<void, unknown, DeleteWebhookInput>({
        mutationFn: async ({ subscriptionId }) =>
            adminConsoleApi.deleteWebhook(subscriptionId),
        onSuccess: (_data, variables) => {
            successToast(
                'Webhook deleted',
                'Subscription removed successfully.',
            );
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
                    : 'Unable to delete webhook.';
            errorToast('Webhook deletion failed', message);
        },
    });
}
