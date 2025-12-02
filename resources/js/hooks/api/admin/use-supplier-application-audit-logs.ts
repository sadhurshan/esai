import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { AuditLogEntry } from '@/types/admin';

interface UseSupplierApplicationAuditLogsOptions {
    enabled?: boolean;
    limit?: number;
}

export function useSupplierApplicationAuditLogs(
    applicationId?: number | null,
    options?: UseSupplierApplicationAuditLogsOptions,
): UseQueryResult<AuditLogEntry[]> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryKey = queryKeys.admin.supplierApplicationAuditLogs(applicationId ?? 'none', {
        limit: options?.limit,
    });

    return useQuery<AuditLogEntry[]>({
        queryKey,
        enabled: Boolean(applicationId) && (options?.enabled ?? true),
        queryFn: async () => {
            if (!applicationId) {
                return [];
            }

            const response = await adminConsoleApi.listSupplierApplicationAuditLogs(applicationId, {
                limit: options?.limit,
            });

            return response.items ?? [];
        },
        staleTime: 15_000,
    });
}
