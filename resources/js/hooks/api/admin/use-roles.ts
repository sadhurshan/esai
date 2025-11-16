import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { useAuth } from '@/contexts/auth-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { AdminRolesPayload } from '@/types/admin';

export function useRoles(): UseQueryResult<AdminRolesPayload> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const { state } = useAuth();
    const companyId = state.company?.id;

    return useQuery<AdminRolesPayload>({
        queryKey: queryKeys.admin.roles(),
        queryFn: async () => adminConsoleApi.listRoles(companyId ?? undefined),
    });
}
