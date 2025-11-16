import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { AdminAnalyticsOverview } from '@/types/admin';

export function useAdminAnalyticsOverview(): UseQueryResult<AdminAnalyticsOverview> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryKey = queryKeys.admin.analyticsOverview();

    return useQuery<AdminAnalyticsOverview>({
        queryKey,
        queryFn: async () => adminConsoleApi.analyticsOverview(),
        staleTime: 60_000,
    });
}
