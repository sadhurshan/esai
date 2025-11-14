import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi } from '@/sdk';

export interface PublishRfqPayload {
    rfqId: string | number;
    dueAt: Date;
    publishAt?: Date | null;
    notifySuppliers?: boolean;
    message?: string;
}

export function usePublishRfq() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, dueAt, publishAt, notifySuppliers, message }: PublishRfqPayload) => {
            return rfqsApi.publishRfq({
                rfqId: String(rfqId),
                publishRfqRequest: {
                    dueAt,
                    publishAt: publishAt ?? undefined,
                    notifySuppliers,
                    message,
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
