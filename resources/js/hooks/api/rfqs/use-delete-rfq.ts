import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi } from '@/sdk';

export interface DeleteRfqPayload {
    rfqId: string | number;
}

export function useDeleteRfq() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId }: DeleteRfqPayload) => {
            return rfqsApi.deleteRfq({
                rfqId: String(rfqId),
            });
        },
        onSuccess: (_response, variables) => {
            const rfqId = String(variables.rfqId);
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.root(),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.detail(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.timeline(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.lines(rfqId),
            });
        },
    });
}
