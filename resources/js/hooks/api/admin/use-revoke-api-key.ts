import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { errorToast, successToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi } from '@/sdk';

interface RevokeApiKeyInput {
    keyId: number;
}

export function useRevokeApiKey(): UseMutationResult<
    void,
    unknown,
    RevokeApiKeyInput
> {
    const adminApi = useSdkClient(AdminApi);
    const queryClient = useQueryClient();

    return useMutation<void, unknown, RevokeApiKeyInput>({
        mutationFn: async ({ keyId }) => {
            await adminApi.adminDeleteApiKey({ keyId });
        },
        onSuccess: () => {
            successToast('API key revoked');
            queryClient.invalidateQueries({
                queryKey: queryKeys.admin.apiKeys(),
            });
        },
        onError: (error) => {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to revoke API key.';
            errorToast('API key revocation failed', message);
        },
    });
}
