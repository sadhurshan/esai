import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

interface MarkReadPayload {
    ids: number[];
}

interface MarkReadResponse {
    updated?: number;
}

export function useMarkRead(): UseMutationResult<MarkReadResponse, ApiError, MarkReadPayload> {
    const queryClient = useQueryClient();

    return useMutation<MarkReadResponse, ApiError, MarkReadPayload>({
        mutationFn: async ({ ids }) => {
            if (!Array.isArray(ids) || ids.length === 0) {
                throw new Error('Select at least one notification to mark as read.');
            }

            return (await api.post<MarkReadResponse>('/notifications/read', {
                ids,
            })) as unknown as MarkReadResponse;
        },
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['notifications'] }),
                queryClient.invalidateQueries({ queryKey: queryKeys.notifications.badge() }),
            ]);
        },
    });
}
