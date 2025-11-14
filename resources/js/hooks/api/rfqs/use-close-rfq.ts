import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi } from '@/sdk';

export interface CloseRfqPayload {
    rfqId: string | number;
    reason?: string;
    closedAt?: Date | null;
}

export function useCloseRfq() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, reason, closedAt }: CloseRfqPayload) => {
            return rfqsApi.closeRfq({
                rfqId: String(rfqId),
                closeRfqRequest: {
                    reason,
                    closedAt: closedAt ?? undefined,
                },
            });
        },
        onSuccess: (_response, variables) => {
            const rfqId = String(variables.rfqId);
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.timeline(rfqId) });
        },
    });
}
