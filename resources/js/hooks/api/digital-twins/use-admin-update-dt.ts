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
    type AdminDigitalTwinMutationPayload,
    type AdminDigitalTwinMutationResponse,
} from '@/sdk';

export interface UseUpdateAdminDigitalTwinVariables extends AdminDigitalTwinMutationPayload {
    digitalTwinId: number | string;
}

export type UseUpdateAdminDigitalTwinResult = UseMutationResult<
    AdminDigitalTwinMutationResponse,
    unknown,
    UseUpdateAdminDigitalTwinVariables
>;

export function useUpdateAdminDigitalTwin(
    options: UseMutationOptions<
        AdminDigitalTwinMutationResponse,
        unknown,
        UseUpdateAdminDigitalTwinVariables
    > = {},
): UseUpdateAdminDigitalTwinResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'admin', 'update'],
        mutationFn: async ({ digitalTwinId, ...payload }) =>
            api.updateDigitalTwin(digitalTwinId, payload),
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
