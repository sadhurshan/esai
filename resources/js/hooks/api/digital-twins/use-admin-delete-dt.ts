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

export interface UseDeleteAdminDigitalTwinVariables {
    digitalTwinId: number | string;
}

export type UseDeleteAdminDigitalTwinResult = UseMutationResult<
    AdminDigitalTwinMutationResponse,
    unknown,
    UseDeleteAdminDigitalTwinVariables
>;

export function useDeleteAdminDigitalTwin(
    options: UseMutationOptions<
        AdminDigitalTwinMutationResponse,
        unknown,
        UseDeleteAdminDigitalTwinVariables
    > = {},
): UseDeleteAdminDigitalTwinResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'admin', 'delete'],
        mutationFn: async ({ digitalTwinId }) =>
            api.deleteDigitalTwin(digitalTwinId),
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
