import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { AiAdminUsageMetrics } from '@/types/admin';

export function useAdminAiUsageMetrics(): UseQueryResult<AiAdminUsageMetrics> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryKey = queryKeys.admin.aiUsageMetrics();

    return useQuery<AiAdminUsageMetrics>({
        queryKey,
        queryFn: async () => adminConsoleApi.aiUsageMetrics(),
        staleTime: 60_000,
    });
}
