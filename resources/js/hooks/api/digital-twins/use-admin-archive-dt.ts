import {
    useMutation,
    useQueryClient,
    type UseMutationOptions,
    type UseMutationResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    AdminDigitalTwinApi,
    type AdminDigitalTwinMutationResponse,
} from '@/sdk';

export interface UseArchiveAdminDigitalTwinVariables {
    digitalTwinId: number | string;
}

export type UseArchiveAdminDigitalTwinResult = UseMutationResult<
    AdminDigitalTwinMutationResponse,
    unknown,
    UseArchiveAdminDigitalTwinVariables
>;

export function useArchiveAdminDigitalTwin(
    options: UseMutationOptions<
        AdminDigitalTwinMutationResponse,
        unknown,
        UseArchiveAdminDigitalTwinVariables
    > = {},
): UseArchiveAdminDigitalTwinResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'admin', 'archive'],
        mutationFn: async ({ digitalTwinId }) =>
            api.archiveDigitalTwin(digitalTwinId),
        ...options,
        onSuccess: (data, variables, onMutateResult, context) => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.digitalTwins.adminRoot(),
            });
            queryClient.invalidateQueries({
                queryKey: queryKeys.digitalTwins.adminDetail(
                    String(variables.digitalTwinId),
                ),
            });

            options.onSuccess?.(data, variables, onMutateResult, context);
        },
    });
}
