import { useMutation, useQueryClient } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { GoodsReceiptNoteDetail } from '@/types/sourcing';
import { HttpError, ReceivingApi } from '@/sdk';

import { mapGrnDetail } from './utils';

export interface AttachGrnFileInput {
    grnId: number;
    file: File | Blob;
    filename?: string;
}

export function useAttachGrnFile() {
    const queryClient = useQueryClient();
    const receivingApi = useSdkClient(ReceivingApi);

    return useMutation<GoodsReceiptNoteDetail, HttpError, AttachGrnFileInput>({
        mutationFn: async ({ grnId, file, filename }) => {
            const response = await receivingApi.attachGrnFile({ grnId, file, filename });
            return mapGrnDetail(response);
        },
        onSuccess: (data) => {
            publishToast({
                variant: 'success',
                title: 'Attachment uploaded',
                description: 'The file has been added to the GRN.',
            });

            if (data.id) {
                void queryClient.invalidateQueries({ queryKey: queryKeys.receiving.detail(data.id) });
            }
            if (data.purchaseOrderId) {
                void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.detail(data.purchaseOrderId) });
            }
            void queryClient.invalidateQueries({ queryKey: queryKeys.receiving.list() });
        },
    });
}
