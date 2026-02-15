import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { CompaniesHouseLookupResponse } from '@/types/admin';

interface UseCompaniesHouseProfileParams {
    companyId: number | null;
    enabled?: boolean;
}

export function useCompaniesHouseProfile({
    companyId,
    enabled = true,
}: UseCompaniesHouseProfileParams): UseQueryResult<CompaniesHouseLookupResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryKey = companyId
        ? queryKeys.admin.companyApprovalsCompaniesHouse(companyId)
        : ['admin', 'company-approvals', 'companies-house', 'pending'];

    return useQuery<CompaniesHouseLookupResponse>({
        queryKey,
        queryFn: async () => {
            if (!companyId) {
                throw new Error(
                    'companyId is required to fetch Companies House data.',
                );
            }

            return adminConsoleApi.fetchCompaniesHouseProfile(companyId);
        },
        enabled: Boolean(companyId) && enabled,
        staleTime: 1000 * 60 * 5,
    });
}
