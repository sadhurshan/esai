import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';

interface ReplayDlqPayload {
    ids: number[];
}

interface ReplayDlqResponse {
    replayed: number;
}

export function useReplayDlq(): UseMutationResult<ReplayDlqResponse, ApiError, ReplayDlqPayload> {
    const queryClient = useQueryClient();

    return useMutation<ReplayDlqResponse, ApiError, ReplayDlqPayload>({
        mutationFn: async ({ ids }) => {
            if (!Array.isArray(ids) || ids.length === 0) {
                throw new Error('Select at least one dead-lettered delivery.');
            }

            return (await api.post<ReplayDlqResponse>('/events/dlq/replay', {
                ids,
            })) as unknown as ReplayDlqResponse;
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['events', 'deliveries'] });
        },
    });
}
