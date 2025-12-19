import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { AiModelMetricFilters, AiModelMetricResponse } from '@/types/admin';

export interface UseAiModelMetricsOptions {
    enabled?: boolean;
}

export function useAiModelMetrics(
    filters: AiModelMetricFilters,
    options: UseAiModelMetricsOptions = {},
): UseQueryResult<AiModelMetricResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const enabled = options.enabled ?? true;
    const serializedFilters = filters ?? {};

    return useQuery<AiModelMetricResponse>({
        queryKey: queryKeys.admin.aiModelMetrics(serializedFilters),
        enabled,
        queryFn: async () => adminConsoleApi.listAiModelMetrics(serializedFilters),
        gcTime: 5 * 60 * 1000,
    });
}
