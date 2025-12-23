import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { SupplierScrapeJobFilters, SupplierScrapeJobListResponse } from '@/types/admin';

export interface UseSupplierScrapeJobsOptions {
    enabled?: boolean;
    refetchInterval?: number | false;
}

export function useSupplierScrapeJobs(
    filters: SupplierScrapeJobFilters | null,
    options: UseSupplierScrapeJobsOptions = {},
): UseQueryResult<SupplierScrapeJobListResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const baseKey = queryKeys.admin.supplierScrapeJobs();
    const enabled = options.enabled ?? Boolean(filters);
    const queryFilters = filters ?? ({} as SupplierScrapeJobFilters);

    return useQuery<SupplierScrapeJobListResponse>({
        queryKey: [...baseKey, queryFilters],
        enabled,
        placeholderData: keepPreviousData,
        refetchInterval: options.refetchInterval,
        queryFn: async () => {
            if (!filters) {
                throw new Error('Filters must be provided to list supplier scrape jobs.');
            }
            return adminConsoleApi.listSupplierScrapeJobs(filters);
        },
    });
}
