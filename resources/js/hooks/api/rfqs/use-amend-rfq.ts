import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    RFQsApi,
    type ApiSuccessResponse,
    type CreateRfqAmendmentRequest,
} from '@/sdk';

export interface AmendRfqPayload {
    rfqId: string | number;
    amendment: CreateRfqAmendmentRequest;
}

export function useAmendRfq() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({
            rfqId,
            amendment,
        }: AmendRfqPayload): Promise<ApiSuccessResponse> => {
            return rfqsApi.createRfqAmendment({
                rfqId: String(rfqId),
                createRfqAmendmentRequest: amendment,
            });
        },
        onSuccess: (_response, variables) => {
            const rfqId = variables.rfqId;
            queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.detail(rfqId),
            });
            queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.clarifications(rfqId),
            });
        },
    });
}
