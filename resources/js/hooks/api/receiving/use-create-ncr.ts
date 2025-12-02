import { useMutation, useQueryClient } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, ReceivingApi } from '@/sdk';

export interface CreateNcrInput {
    grnId: number;
    poLineId: number;
    reason: string;
    disposition?: 'rework' | 'return' | 'accept_as_is';
}

export function useCreateNcr() {
    const queryClient = useQueryClient();
    const receivingApi = useSdkClient(ReceivingApi);

    return useMutation<void, HttpError, CreateNcrInput>({
        mutationFn: async ({ grnId, poLineId, reason, disposition }) => {
            await receivingApi.createNcr({
                grnId,
                poLineId,
                reason,
                disposition,
            });
        },
        onSuccess: (_, variables) => {
            publishToast({
                variant: 'success',
                title: 'NCR raised',
                description: 'The non-conformance record is now tracking this line.',
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.receiving.detail(variables.grnId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.receiving.list() });
        },
    });
}
