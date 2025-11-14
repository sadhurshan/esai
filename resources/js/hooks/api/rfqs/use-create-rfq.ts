import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type CreateRfq201Response, type CreateRfqRequest } from '@/sdk';

export interface UseCreateRfqOptions {
    onSuccess?: (response: CreateRfq201Response) => void;
}

export function useCreateRfq(options: UseCreateRfqOptions = {}) {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: CreateRfqRequest) => rfqsApi.createRfq(payload),
        onSuccess: (response: CreateRfq201Response) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.root() });
            if (options.onSuccess) {
                options.onSuccess(response);
            }
        },
    });
}
