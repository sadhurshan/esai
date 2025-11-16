import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { successToast, errorToast } from '@/components/toasts';
import { useSdkClient } from '@/contexts/api-client-context';
import { useAuth } from '@/contexts/auth-context';
import { queryKeys } from '@/lib/queryKeys';
import { AdminApi, type AdminCreateRateLimitRequest, type AdminUpdateRateLimitRequest } from '@/sdk';
import type { SyncRateLimitPayload } from '@/types/admin';

export function useUpdateRateLimits(): UseMutationResult<void, unknown, SyncRateLimitPayload> {
    const adminApi = useSdkClient(AdminApi);
    const queryClient = useQueryClient();
    const { state } = useAuth();
    const companyId = state.company?.id;

    return useMutation<void, unknown, SyncRateLimitPayload>({
        mutationFn: async ({ upserts, removals }) => {
            const createQueue = Array.isArray(upserts) ? upserts : [];
            const removalQueue = Array.isArray(removals) ? removals : [];

            for (const draft of createQueue) {
                if (!draft.scope?.trim()) {
                    continue;
                }

                if (draft.id) {
                    const request: AdminUpdateRateLimitRequest = {
                        windowSeconds: draft.windowSeconds,
                        maxRequests: draft.maxRequests,
                        active: draft.active,
                    };
                    await adminApi.adminUpdateRateLimit({
                        rateLimitId: draft.id,
                        adminUpdateRateLimitRequest: request,
                    });
                } else {
                    const request: AdminCreateRateLimitRequest = {
                        scope: draft.scope.trim(),
                        windowSeconds: draft.windowSeconds ?? 0,
                        maxRequests: draft.maxRequests ?? 0,
                        active: draft.active ?? true,
                        companyId: draft.companyId ?? companyId ?? undefined,
                    };
                    await adminApi.adminCreateRateLimit({
                        adminCreateRateLimitRequest: request,
                    });
                }
            }

            for (const id of removalQueue) {
                if (typeof id !== 'number') {
                    continue;
                }
                await adminApi.adminDeleteRateLimit({ rateLimitId: id });
            }
        },
        onSuccess: () => {
            successToast('Rate limits updated', 'Throttle rules synced successfully.');
            queryClient.invalidateQueries({ queryKey: queryKeys.admin.rateLimits() });
        },
        onError: (error) => {
            const message = error instanceof Error ? error.message : 'Unable to sync rate limits.';
            errorToast('Rate limit sync failed', message);
        },
    });
}
