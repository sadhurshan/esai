import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { useAuth } from '@/contexts/auth-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi, type RateLimitRule } from '@/sdk';

interface RateLimitResponse {
    items: RateLimitRule[];
}

export function useRateLimits(): UseQueryResult<RateLimitResponse> {
    const adminApi = useSdkClient(AdminApi);
    const { state } = useAuth();
    const companyId = state.company?.id;

    return useQuery<RateLimitResponse>({
        queryKey: queryKeys.admin.rateLimits(),
        queryFn: async () => {
            const response = await adminApi.adminListRateLimits({ companyId });
            return {
                items: response.data.items ?? [],
            } satisfies RateLimitResponse;
        },
    });
}
