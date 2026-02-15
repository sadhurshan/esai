import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { errorToast, successToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminConsoleApi } from '@/sdk';
import type { UpdateRolePayload } from '@/types/admin';

export function useUpdateRole(): UseMutationResult<
    void,
    unknown,
    UpdateRolePayload
> {
    const adminConsoleApi = useSdkClient(AdminConsoleApi);
    const queryClient = useQueryClient();

    return useMutation<void, unknown, UpdateRolePayload>({
        mutationFn: async (payload) => {
            await adminConsoleApi.updateRole(payload);
        },
        onSuccess: () => {
            successToast('Role updated', 'Permissions saved successfully.');
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.roles(),
            });
        },
        onError: () => {
            errorToast('Unable to update role', 'Please try again.');
        },
    });
}
