import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type {
    CursorPaginatedResponse,
    ListWebhookDeliveriesParams,
    WebhookDeliveryItem,
} from '@/types/admin';

export function useWebhookDeliveries(
    params: ListWebhookDeliveriesParams = {},
): UseQueryResult<CursorPaginatedResponse<WebhookDeliveryItem>> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const subscriptionKey = params.subscriptionId
        ? String(params.subscriptionId)
        : 'all';

    return useQuery<CursorPaginatedResponse<WebhookDeliveryItem>>({
        queryKey: queryKeys.admin.webhookDeliveries(subscriptionKey, params),
        enabled: Boolean(params.subscriptionId),
        queryFn: async () => adminConsoleApi.listWebhookDeliveries(params),
    });
}
