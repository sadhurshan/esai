import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { StartSupplierScrapePayload, SupplierScrapeJob } from '@/types/admin';

export function useStartSupplierScrape(): UseMutationResult<
    SupplierScrapeJob,
    unknown,
    StartSupplierScrapePayload
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<SupplierScrapeJob, unknown, StartSupplierScrapePayload>({
        mutationFn: (payload) => adminConsoleApi.startSupplierScrape(payload),
        onSuccess: (job) => {
            const scope = job.company_id ? `company ${job.company_id}` : 'the global discovery queue';
            successToast('Scrape started', `Job #${job.id} queued for ${scope}.`);
            queryClient.invalidateQueries({ queryKey: queryKeys.admin.supplierScrapeJobs(), exact: false });
        },
        onError: (error) => {
            const message = error instanceof Error ? error.message : 'Unexpected error';
            errorToast('Unable to start scrape', message);
        },
    });
}
