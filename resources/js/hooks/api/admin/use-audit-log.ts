import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { AuditLogFilters, AuditLogResponse } from '@/types/admin';

export interface UseAuditLogOptions {
    enabled?: boolean;
}

export function useAuditLog(filters: AuditLogFilters, options: UseAuditLogOptions = {}): UseQueryResult<AuditLogResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const enabled = options.enabled ?? true;
    const serializedFilters = filters ?? {};

    return useQuery<AuditLogResponse>({
        queryKey: queryKeys.admin.auditLog(serializedFilters),
        enabled,
        queryFn: async () => adminConsoleApi.listAuditLog(serializedFilters),
        gcTime: 5 * 60 * 1000,
    });
}
