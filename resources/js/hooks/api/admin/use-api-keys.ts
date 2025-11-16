import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { useAuth } from '@/contexts/auth-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi, type ApiKey } from '@/sdk';

interface ApiKeysResponse {
    items: ApiKey[];
}

export function useApiKeys(): UseQueryResult<ApiKeysResponse> {
    const adminApi = useSdkClient(AdminApi);
    const { state } = useAuth();
    const companyId = state.company?.id;

    return useQuery<ApiKeysResponse>({
        queryKey: queryKeys.admin.apiKeys(),
        queryFn: async () => {
            const response = await adminApi.adminListApiKeys({ companyId });
            return {
                items: response.data.items ?? [],
            };
        },
    });
}
