import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';

interface RetryDeliveryPayload {
    id: number;
}

interface RetryDeliveryResponse {
    id: number;
}

export function useRetryDelivery(): UseMutationResult<
    RetryDeliveryResponse,
    ApiError,
    RetryDeliveryPayload
> {
    const queryClient = useQueryClient();

    return useMutation<RetryDeliveryResponse, ApiError, RetryDeliveryPayload>({
        mutationFn: async ({ id }) => {
            return (await api.post<RetryDeliveryResponse>(
                `/events/deliveries/${id}/retry`,
            )) as unknown as RetryDeliveryResponse;
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({
                queryKey: ['events', 'deliveries'],
            });
        },
    });
}
