import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type UploadRfqAttachment201Response } from '@/sdk';

export interface UploadAttachmentPayload {
    rfqId: string | number;
    file: File;
}

export function useUploadAttachment() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, file }: UploadAttachmentPayload): Promise<UploadRfqAttachment201Response> => {
            return rfqsApi.uploadRfqAttachment({
                rfqId: String(rfqId),
                file,
            });
        },
        onSuccess: (_response, variables) => {
            const rfqId = String(variables.rfqId);
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.attachments(rfqId) });
        },
    });
}
