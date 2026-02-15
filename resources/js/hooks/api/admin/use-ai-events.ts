import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { AiEventFilters, AiEventResponse } from '@/types/admin';

export interface UseAiEventsOptions {
    enabled?: boolean;
}

export function useAiEvents(
    filters: AiEventFilters,
    options: UseAiEventsOptions = {},
): UseQueryResult<AiEventResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const enabled = options.enabled ?? true;
    const serializedFilters = filters ?? {};

    return useQuery<AiEventResponse>({
        queryKey: queryKeys.admin.aiEvents(serializedFilters),
        enabled,
        queryFn: async () => adminConsoleApi.listAiEvents(serializedFilters),
        gcTime: 5 * 60 * 1000,
    });
}
