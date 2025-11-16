import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { useAuth } from '@/contexts/auth-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi, type AdminCreateApiKeyRequest } from '@/sdk';
import type { ApiKeyIssueResult } from '@/types/admin';

export interface CreateApiKeyInput {
    name: string;
    scopes: string[];
    expiresAt?: string | Date | null;
}

export function useCreateApiKey(): UseMutationResult<ApiKeyIssueResult, unknown, CreateApiKeyInput> {
    const adminApi = useSdkClient(AdminApi);
    const queryClient = useQueryClient();
    const { state } = useAuth();
    const companyId = state.company?.id;

    return useMutation<ApiKeyIssueResult, unknown, CreateApiKeyInput>({
        mutationFn: async ({ name, scopes, expiresAt }) => {
            if (!companyId) {
                throw new Error('Missing company context for API key creation.');
            }

            const payload: AdminCreateApiKeyRequest = {
                companyId,
                name: name.trim(),
                scopes,
            };

            if (expiresAt) {
                payload.expiresAt = expiresAt instanceof Date ? expiresAt : new Date(expiresAt);
            }

            const response = await adminApi.adminCreateApiKey({
                adminCreateApiKeyRequest: payload,
            });

            const token = response.data.token ?? '';
            const apiKey = response.data.apiKey;

            if (!token || !apiKey) {
                throw new Error('API key response missing secret or resource.');
            }

            successToast('API key created', 'Copy the token now; it will not be shown again.');
            queryClient.invalidateQueries({ queryKey: queryKeys.admin.apiKeys() });

            return { token, apiKey };
        },
        onError: (error) => {
            const message = error instanceof Error ? error.message : 'Unable to create API key.';
            errorToast('API key creation failed', message);
        },
    });
}
