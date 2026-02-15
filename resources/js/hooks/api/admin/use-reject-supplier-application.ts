import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { errorToast, successToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { SupplierApplicationItem } from '@/types/admin';

interface RejectSupplierApplicationPayload {
    applicationId: number;
    notes: string;
}

export function useRejectSupplierApplication(): UseMutationResult<
    SupplierApplicationItem,
    unknown,
    RejectSupplierApplicationPayload
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<
        SupplierApplicationItem,
        unknown,
        RejectSupplierApplicationPayload
    >({
        mutationFn: ({ applicationId, notes }) =>
            adminConsoleApi.rejectSupplierApplication(applicationId, { notes }),
        onSuccess: (application) => {
            const companyName = application.company?.name ?? 'Supplier';
            successToast(
                'Supplier rejected',
                `${companyName} has been notified.`,
            );
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.supplierApplications(),
                exact: false,
            });
        },
        onError: () => {
            errorToast('Unable to reject supplier', 'Please try again.');
        },
    });
}
