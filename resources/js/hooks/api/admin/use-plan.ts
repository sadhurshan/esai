import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi, type Plan } from '@/sdk';

export interface UsePlanOptions {
    enabled?: boolean;
}

export function usePlan(planId: string | number | null | undefined, options: UsePlanOptions = {}): UseQueryResult<Plan> {
    const adminApi = useSdkClient(AdminApi);
    const enabled = options.enabled ?? Boolean(planId);

    return useQuery<Plan>({
        queryKey: queryKeys.admin.plan(planId ?? 'undefined'),
        enabled,
        queryFn: async () => {
            const response = await adminApi.adminPlansShow({
                planId: Number(planId),
            });
            return response.data as unknown as Plan;
        },
    });
}
