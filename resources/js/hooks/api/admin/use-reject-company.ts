import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { errorToast, successToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { CompanyApprovalItem } from '@/types/admin';

interface RejectCompanyPayload {
    companyId: number;
    reason: string;
}

export function useRejectCompany(): UseMutationResult<
    CompanyApprovalItem,
    unknown,
    RejectCompanyPayload
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<CompanyApprovalItem, unknown, RejectCompanyPayload>({
        mutationFn: async ({ companyId, reason }) =>
            adminConsoleApi.rejectCompany(companyId, { reason }),
        onSuccess: (company) => {
            successToast(
                'Company rejected',
                `${company.name} was marked as rejected.`,
            );
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.companyApprovals(),
                exact: false,
            });
        },
        onError: () => {
            errorToast(
                'Unable to reject company',
                'Please add a reason and try again.',
            );
        },
    });
}
