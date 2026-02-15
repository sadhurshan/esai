import { useMutation, useQueryClient } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, ReceivingApi } from '@/sdk';

export interface CloseNcrInput {
    grnId: number;
    ncrId: number;
    disposition?: 'rework' | 'return' | 'accept_as_is';
}

export function useCloseNcr() {
    const queryClient = useQueryClient();
    const receivingApi = useSdkClient(ReceivingApi);

    return useMutation<void, HttpError, CloseNcrInput>({
        mutationFn: async ({ ncrId, disposition }) => {
            await receivingApi.updateNcr({
                ncrId,
                disposition,
            });
        },
        onSuccess: (_, variables) => {
            publishToast({
                variant: 'success',
                title: 'NCR closed',
                description: 'The NCR has been marked as resolved.',
            });

            void queryClient.invalidateQueries({
                queryKey: queryKeys.receiving.detail(variables.grnId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.receiving.list(),
            });
        },
    });
}
