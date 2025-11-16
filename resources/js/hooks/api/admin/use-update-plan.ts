import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi, type AdminPlansUpdateRequest } from '@/sdk';

export interface UpdatePlanInput {
    planId: number;
    payload: AdminPlansUpdateRequest;
}

export function useUpdatePlan(): UseMutationResult<void, unknown, UpdatePlanInput> {
    const adminApi = useSdkClient(AdminApi);
    const queryClient = useQueryClient();

    return useMutation<void, unknown, UpdatePlanInput>({
        mutationFn: async ({ planId, payload }) => {
            await adminApi.adminPlansUpdate({
                planId,
                adminPlansUpdateRequest: payload,
            });
        },
        onSuccess: (_data, variables) => {
            successToast('Plan updated', 'Feature matrix saved successfully.');
            queryClient.invalidateQueries({ queryKey: queryKeys.admin.plans() });
            queryClient.invalidateQueries({ queryKey: queryKeys.admin.plan(variables.planId) });
        },
        onError: () => {
            errorToast('Unable to update plan', 'Please review your changes and try again.');
        },
    });
}
