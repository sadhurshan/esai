import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type ApiSuccessResponse } from '@/sdk';

export interface InviteSuppliersPayload {
    rfqId: string | number;
    supplierIds: (string | number)[];
}

export interface InviteSuppliersResult {
    invited: number;
    responses: ApiSuccessResponse[];
}

export function useInviteSuppliers() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, supplierIds }: InviteSuppliersPayload): Promise<InviteSuppliersResult> => {
            if (supplierIds.length === 0) {
                return { invited: 0, responses: [] } satisfies InviteSuppliersResult;
            }

            const responses: ApiSuccessResponse[] = [];
            for (const supplierId of supplierIds) {
                const response = await rfqsApi.inviteSupplierToRfq({
                    rfqId: String(rfqId),
                    inviteSupplierToRfqRequest: {
                        supplierId: String(supplierId),
                    },
                });
                responses.push(response);
            }

            return {
                invited: responses.length,
                responses,
            } satisfies InviteSuppliersResult;
        },
        onSuccess: (_result, variables) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.suppliers(variables.rfqId) });
        },
    });
}
