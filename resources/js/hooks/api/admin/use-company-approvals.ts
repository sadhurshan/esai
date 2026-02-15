import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type {
    CompanyApprovalFilters,
    CompanyApprovalResponse,
} from '@/types/admin';

export function useCompanyApprovals(
    params: CompanyApprovalFilters = {},
): UseQueryResult<CompanyApprovalResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const baseKey = queryKeys.admin.companyApprovals();

    return useQuery<CompanyApprovalResponse>({
        queryKey: [...baseKey, params],
        queryFn: async () => adminConsoleApi.listCompanyApprovals(params),
        placeholderData: keepPreviousData,
    });
}
