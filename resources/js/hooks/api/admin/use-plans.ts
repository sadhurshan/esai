import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi, type Plan } from '@/sdk';

interface PlansResponse {
    items: Plan[];
}

export function usePlans(): UseQueryResult<PlansResponse> {
    const adminApi = useSdkClient(AdminApi);

    return useQuery<PlansResponse>({
        queryKey: queryKeys.admin.plans(),
        queryFn: async () => {
            const response = await adminApi.adminPlansIndex();
            return {
                items: response.data.items ?? [],
            };
        },
    });
}
