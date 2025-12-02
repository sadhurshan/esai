import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { SupplierApplicationFilters, SupplierApplicationResponse } from '@/types/admin';

export function useAdminSupplierApplications(
    params: SupplierApplicationFilters = {},
): UseQueryResult<SupplierApplicationResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const baseKey = queryKeys.admin.supplierApplications();

    return useQuery<SupplierApplicationResponse>({
        queryKey: [...baseKey, params],
        queryFn: async () => adminConsoleApi.listSupplierApplications(params),
        placeholderData: keepPreviousData,
    });
}
