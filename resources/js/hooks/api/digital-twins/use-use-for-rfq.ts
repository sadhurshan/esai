import { useMutation, useQueryClient, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { DigitalTwinLibraryApi, type DigitalTwinUseForRfqResponse } from '@/sdk';

export interface UseUseDigitalTwinForRfqVariables {
    digitalTwinId: number | string;
}

export type UseUseForRfqResult = UseMutationResult<
    DigitalTwinUseForRfqResponse,
    unknown,
    UseUseDigitalTwinForRfqVariables
>;

export function useUseForRfq(
    options: UseMutationOptions<DigitalTwinUseForRfqResponse, unknown, UseUseDigitalTwinForRfqVariables> = {},
): UseUseForRfqResult {
    const api = useSdkClient(DigitalTwinLibraryApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationKey: ['digital-twins', 'library', 'use-for-rfq'],
        mutationFn: async ({ digitalTwinId }) => api.useDigitalTwinForRfq(digitalTwinId),
        ...options,
        onSuccess: (data, variables, onMutateResult, context) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.digitalTwins.libraryRoot() });
            queryClient.invalidateQueries({
                queryKey: queryKeys.digitalTwins.libraryDetail(String(variables.digitalTwinId)),
            });

            options.onSuccess?.(data, variables, onMutateResult, context);
        },
    });
}
