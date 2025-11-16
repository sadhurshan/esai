import { useMutation, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { AdminConsoleApi } from '@/sdk';
import type { WebhookTestPayload } from '@/types/admin';

export interface SendWebhookTestInput extends WebhookTestPayload {
    subscriptionId: string;
}

export function useTestWebhook(): UseMutationResult<void, unknown, SendWebhookTestInput> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);

    return useMutation<void, unknown, SendWebhookTestInput>({
        mutationFn: async ({ subscriptionId, ...payload }) =>
            adminConsoleApi.sendWebhookTest(subscriptionId, payload),
        onSuccess: () => {
            successToast('Test queued', 'Webhook test event dispatched.');
        },
        onError: (error) => {
            const message = error instanceof Error ? error.message : 'Unable to send webhook test.';
            errorToast('Webhook test failed', message);
        },
    });
}
