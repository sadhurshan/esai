import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type {
    ScrapedSupplierFilters,
    ScrapedSupplierListResponse,
} from '@/types/admin';

export interface UseScrapedSuppliersOptions {
    enabled?: boolean;
    refetchInterval?: number | false;
}

export function useScrapedSuppliers(
    jobId: string | number | null,
    filters: ScrapedSupplierFilters = {},
    options: UseScrapedSuppliersOptions = {},
): UseQueryResult<ScrapedSupplierListResponse> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const normalizedJobId = jobId ? String(jobId) : 'unknown';
    const baseKey = queryKeys.admin.scrapedSuppliers(normalizedJobId);
    const enabled = options.enabled ?? Boolean(jobId);

    return useQuery<ScrapedSupplierListResponse>({
        queryKey: [...baseKey, filters ?? {}],
        enabled,
        placeholderData: keepPreviousData,
        refetchInterval: options.refetchInterval,
        queryFn: async () => {
            if (!jobId) {
                throw new Error('jobId is required to load scraped suppliers.');
            }
            return adminConsoleApi.listScrapedSuppliers(jobId, filters);
        },
    });
}
