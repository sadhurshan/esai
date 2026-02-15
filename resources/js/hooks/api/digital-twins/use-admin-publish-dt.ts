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

export interface UsePublishAdminDigitalTwinVariables {
    digitalTwinId: number | string;
}

export type UsePublishAdminDigitalTwinResult = UseMutationResult<
    AdminDigitalTwinMutationResponse,
    unknown,
    UsePublishAdminDigitalTwinVariables
>;

export function usePublishAdminDigitalTwin(
    options: UseMutationOptions<
        AdminDigitalTwinMutationResponse,
        unknown,
        UsePublishAdminDigitalTwinVariables
    > = {},
): UsePublishAdminDigitalTwinResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'admin', 'publish'],
        mutationFn: async ({ digitalTwinId }) =>
            api.publishDigitalTwin(digitalTwinId),
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
