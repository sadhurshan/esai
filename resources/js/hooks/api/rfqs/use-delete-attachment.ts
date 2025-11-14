import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type ApiSuccessResponse } from '@/sdk';

export interface DeleteAttachmentPayload {
    rfqId: string | number;
    attachmentId: string | number;
}

export function useDeleteAttachment() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, attachmentId }: DeleteAttachmentPayload): Promise<ApiSuccessResponse> => {
            return rfqsApi.deleteRfqAttachment({
                rfqId: String(rfqId),
                attachmentId: String(attachmentId),
            });
        },
        onSuccess: (_response, variables) => {
            const rfqId = String(variables.rfqId);
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.attachments(rfqId) });
        },
    });
}
