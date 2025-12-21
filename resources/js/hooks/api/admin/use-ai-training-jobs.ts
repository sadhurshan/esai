import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { ModelTrainingJobFilters, ModelTrainingJobListResponse } from '@/types/admin';

export interface UseAiTrainingJobsOptions {
    enabled?: boolean;
    refetchInterval?: number | false;
}

export function useAiTrainingJobs(
    filters: ModelTrainingJobFilters,
    options: UseAiTrainingJobsOptions = {},
): UseQueryResult<ModelTrainingJobListResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const enabled = options.enabled ?? true;
    const queryFilters = filters ?? {};

    return useQuery<ModelTrainingJobListResponse>({
        queryKey: queryKeys.admin.aiTrainingJobs(queryFilters),
        enabled,
        queryFn: async () => adminConsoleApi.listAiTrainingJobs(queryFilters),
        refetchInterval: options.refetchInterval,
        gcTime: 5 * 60 * 1000,
    });
}
