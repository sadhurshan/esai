import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { ScrapedSupplier } from '@/types/admin';

interface DiscardScrapedSupplierInput {
    jobId: string | number;
    scrapedSupplierId: string | number;
    notes?: string | null;
}

export function useDiscardScrapedSupplier(): UseMutationResult<
    ScrapedSupplier,
    unknown,
    DiscardScrapedSupplierInput
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<ScrapedSupplier, unknown, DiscardScrapedSupplierInput>({
        mutationFn: ({ scrapedSupplierId, notes }) =>
            adminConsoleApi.discardScrapedSupplier(scrapedSupplierId, { notes }),
        onSuccess: (_scrapedSupplier, variables) => {
            successToast('Supplier discarded', 'Record removed from the review queue.');
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.scrapedSuppliers(String(variables.jobId)),
                exact: false,
            });
        },
        onError: (error) => {
            const message = error instanceof Error ? error.message : 'Unexpected error';
            errorToast('Unable to discard supplier', message);
        },
    });
}
