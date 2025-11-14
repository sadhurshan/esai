import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type CreateRfq201Response, type UpdateRfqRequest } from '@/sdk';

export interface UseUpdateRfqOptions {
    onSuccess?: (response: CreateRfq201Response) => void;
}

export function useUpdateRfq(options: UseUpdateRfqOptions = {}) {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: UpdateRfqRequest) => rfqsApi.updateRfq(payload),
        onSuccess: (response: CreateRfq201Response) => {
            const rfqId = response.data.id;
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.root() });
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
            if (options.onSuccess) {
                options.onSuccess(response);
            }
        },
    });
}
