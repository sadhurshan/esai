import { useMutation, useQueryClient, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
	AdminDigitalTwinApi,
	type AdminDigitalTwinMutationPayload,
	type AdminDigitalTwinMutationResponse,
} from '@/sdk';

export type UseCreateAdminDigitalTwinVariables = AdminDigitalTwinMutationPayload;

export type UseCreateAdminDigitalTwinResult = UseMutationResult<
	AdminDigitalTwinMutationResponse,
	unknown,
	UseCreateAdminDigitalTwinVariables
>;

export function useCreateAdminDigitalTwin(
	options: UseMutationOptions<
		AdminDigitalTwinMutationResponse,
		unknown,
		UseCreateAdminDigitalTwinVariables
	> = {},
): UseCreateAdminDigitalTwinResult {
	const api = useSdkClient(AdminDigitalTwinApi);
	const queryClient = useQueryClient();

	return useMutation({
		mutationKey: ['digital-twins', 'admin', 'create'],
		mutationFn: async (payload) => api.createDigitalTwin(payload),
		...options,
		onSuccess: (data, variables, onMutateResult, context) => {
			queryClient.invalidateQueries({ queryKey: queryKeys.digitalTwins.adminRoot() });

			const createdId = data.data?.digital_twin?.id;
			if (createdId) {
				queryClient.invalidateQueries({
					queryKey: queryKeys.digitalTwins.adminDetail(String(createdId)),
				});
			}

			options.onSuccess?.(data, variables, onMutateResult, context);
		},
	});
}
