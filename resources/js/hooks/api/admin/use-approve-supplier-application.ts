import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { SupplierApplicationItem } from '@/types/admin';

interface ApproveSupplierApplicationPayload {
    applicationId: number;
    notes?: string | null;
}

export function useApproveSupplierApplication(): UseMutationResult<
    SupplierApplicationItem,
    unknown,
    ApproveSupplierApplicationPayload
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<SupplierApplicationItem, unknown, ApproveSupplierApplicationPayload>({
        mutationFn: ({ applicationId, notes }) =>
            adminConsoleApi.approveSupplierApplication(applicationId, { notes }),
        onSuccess: (application) => {
            const companyName = application.company?.name ?? 'Supplier';
            successToast('Supplier approved', `${companyName} is now verified.`);
            queryClient.invalidateQueries({ queryKey: queryKeys.admin.supplierApplications(), exact: false });
        },
        onError: () => {
            errorToast('Unable to approve supplier', 'Please try again.');
        },
    });
}
