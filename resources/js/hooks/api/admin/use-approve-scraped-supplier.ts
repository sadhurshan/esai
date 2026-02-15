import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { errorToast, successToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type {
    ApproveScrapedSupplierPayload,
    ScrapedSupplier,
} from '@/types/admin';

export interface ApproveScrapedSupplierInput {
    jobId: string | number;
    scrapedSupplierId: string | number;
    payload: ApproveScrapedSupplierPayload;
}

export function useApproveScrapedSupplier(): UseMutationResult<
    ScrapedSupplier,
    unknown,
    ApproveScrapedSupplierInput
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<ScrapedSupplier, unknown, ApproveScrapedSupplierInput>({
        mutationFn: ({ scrapedSupplierId, payload }) =>
            adminConsoleApi.approveScrapedSupplier(scrapedSupplierId, payload),
        onSuccess: (scrapedSupplier, variables) => {
            successToast(
                'Supplier onboarded',
                `${scrapedSupplier.name} is now active.`,
            );
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.scrapedSuppliers(
                    String(variables.jobId),
                ),
                exact: false,
            });
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.supplierScrapeJobs(),
                exact: false,
            });
        },
        onError: (error) => {
            const message =
                error instanceof Error ? error.message : 'Unexpected error';
            errorToast('Unable to approve supplier', message);
        },
    });
}
