import { useMutation, useQueryClient, type UseMutationOptions, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
	AdminDigitalTwinApi,
	type AdminDigitalTwinAssetResponse,
	type UploadDigitalTwinAssetParams,
} from '@/sdk';

export interface UseUploadAdminDigitalTwinAssetVariables extends UploadDigitalTwinAssetParams {
	digitalTwinId: number | string;
}

export type UseUploadAdminDigitalTwinAssetResult = UseMutationResult<
	AdminDigitalTwinAssetResponse,
	unknown,
	UseUploadAdminDigitalTwinAssetVariables
>;

export function useUploadAdminDigitalTwinAsset(
	options: UseMutationOptions<
		AdminDigitalTwinAssetResponse,
		unknown,
		UseUploadAdminDigitalTwinAssetVariables
	> = {},
): UseUploadAdminDigitalTwinAssetResult {
	const api = useSdkClient(AdminDigitalTwinApi);
	const queryClient = useQueryClient();

	return useMutation({
		mutationKey: ['digital-twins', 'admin', 'upload-asset'],
		mutationFn: async ({ digitalTwinId, ...params }) => api.uploadDigitalTwinAsset(digitalTwinId, params),
		...options,
		onSuccess: (data, variables, onMutateResult, context) => {
			queryClient.invalidateQueries({
				queryKey: queryKeys.digitalTwins.adminDetail(String(variables.digitalTwinId)),
			});

			options.onSuccess?.(data, variables, onMutateResult, context);
		},
	});
}
