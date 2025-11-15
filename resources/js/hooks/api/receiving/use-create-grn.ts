import { useMutation, useQueryClient } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { GoodsReceiptNoteDetail } from '@/types/sourcing';
import { HttpError, ReceivingApi } from '@/sdk';

import { mapGrnDetail } from './utils';

export interface CreateGrnLineInput {
    poLineId: number;
    quantityReceived: number;
    uom?: string;
    notes?: string;
}

export interface CreateGrnInput {
    purchaseOrderId: number;
    receivedAt?: string;
    reference?: string;
    notes?: string;
    status?: 'draft' | 'posted';
    lines: CreateGrnLineInput[];
}

export function useCreateGrn() {
    const queryClient = useQueryClient();
    const receivingApi = useSdkClient(ReceivingApi);

    return useMutation<GoodsReceiptNoteDetail, HttpError, CreateGrnInput>({
        mutationFn: async (payload) => {
            const response = await receivingApi.createGrn({
                purchaseOrderId: payload.purchaseOrderId,
                receivedAt: payload.receivedAt,
                reference: payload.reference,
                notes: payload.notes,
                status: payload.status,
                lines: payload.lines.map((line) => ({
                    poLineId: line.poLineId,
                    quantityReceived: line.quantityReceived,
                    uom: line.uom,
                    notes: line.notes,
                })),
            });

            return mapGrnDetail(response);
        },
        onSuccess: (data, variables) => {
            publishToast({
                variant: 'success',
                title: variables.status === 'posted' ? 'GRN posted' : 'GRN saved',
                description: `Goods receipt ${data.grnNumber ?? ''} has been ${variables.status === 'posted' ? 'posted' : 'saved as draft'}.`,
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.receiving.list() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.detail(variables.purchaseOrderId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.list({}) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.matching.candidates({}) });
            if (data.id) {
                void queryClient.invalidateQueries({ queryKey: queryKeys.receiving.detail(data.id) });
            }
        },
    });
}
