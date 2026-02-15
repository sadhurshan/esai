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

interface ApproveCompanyPayload {
    companyId: number;
}

export function useApproveCompany(): UseMutationResult<
    CompanyApprovalItem,
    unknown,
    ApproveCompanyPayload
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<CompanyApprovalItem, unknown, ApproveCompanyPayload>({
        mutationFn: async ({ companyId }) =>
            adminConsoleApi.approveCompany(companyId),
        onSuccess: (company) => {
            successToast('Company approved', `${company.name} is now active.`);
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.companyApprovals(),
                exact: false,
            });
        },
        onError: () => {
            errorToast('Unable to approve company', 'Please try again.');
        },
    });
}
