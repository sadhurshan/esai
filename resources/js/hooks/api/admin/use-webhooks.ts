import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type {
    CursorPaginatedResponse,
    ListWebhooksParams,
    WebhookSubscriptionItem,
} from '@/types/admin';

export function useWebhooks(
    params: ListWebhooksParams = {},
): UseQueryResult<CursorPaginatedResponse<WebhookSubscriptionItem>> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const baseKey = queryKeys.admin.webhooks();

    return useQuery<CursorPaginatedResponse<WebhookSubscriptionItem>>({
        queryKey: [...baseKey, params],
        queryFn: async () => adminConsoleApi.listWebhooks(params),
    });
}
