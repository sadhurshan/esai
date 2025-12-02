import { useMutation, useQueryClient, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminDigitalTwinApi, type AdminDigitalTwinMutationResponse } from '@/sdk';

export interface UseDeleteAdminDigitalTwinAssetVariables {
	digitalTwinId: number | string;
	assetId: number | string;
}

export type UseDeleteAdminDigitalTwinAssetResult = UseMutationResult<
	AdminDigitalTwinMutationResponse,
	unknown,
	UseDeleteAdminDigitalTwinAssetVariables
>;

export function useDeleteAdminDigitalTwinAsset(
	options: UseMutationOptions<
		AdminDigitalTwinMutationResponse,
		unknown,
		UseDeleteAdminDigitalTwinAssetVariables
	> = {},
): UseDeleteAdminDigitalTwinAssetResult {
	const api = useSdkClient(AdminDigitalTwinApi);
	const queryClient = useQueryClient();

	return useMutation({
		mutationKey: ['digital-twins', 'admin', 'delete-asset'],
		mutationFn: async ({ digitalTwinId, assetId }) => api.deleteDigitalTwinAsset(digitalTwinId, assetId),
		...options,
		onSuccess: (data, variables, onMutateResult, context) => {
			queryClient.invalidateQueries({
				queryKey: queryKeys.digitalTwins.adminDetail(String(variables.digitalTwinId)),
			});

			options.onSuccess?.(data, variables, onMutateResult, context);
		},
	});
}
