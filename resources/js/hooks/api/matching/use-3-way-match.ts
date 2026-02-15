import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, MatchingApi } from '@/sdk';
import type { MatchResolutionInput } from '@/types/sourcing';

export const use3WayMatch = (): UseMutationResult<
    Record<string, unknown>,
    HttpError,
    MatchResolutionInput
> => {
    const queryClient = useQueryClient();
    const matchingApi = useSdkClient(MatchingApi);

    return useMutation<
        Record<string, unknown>,
        HttpError,
        MatchResolutionInput
    >({
        mutationFn: async (payload) =>
            matchingApi.resolveMatch({
                invoiceId: payload.invoiceId,
                purchaseOrderId: payload.purchaseOrderId,
                grnIds: payload.grnIds,
                decisions: payload.decisions,
            }),
        onSuccess: (_, variables) => {
            publishToast({
                variant: 'success',
                title: 'Match resolved',
                description:
                    'Discrepancies have been reconciled across PO, GRN, and invoice.',
            });

            void queryClient.invalidateQueries({
                queryKey: ['matching', 'candidates'],
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.detail(
                    variables.purchaseOrderId,
                ),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.root(),
            });
            void queryClient.invalidateQueries({ queryKey: ['receiving'] });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.invoices.detail(variables.invoiceId),
            });
            void queryClient.invalidateQueries({ queryKey: ['invoices'] });
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to resolve match',
                description:
                    error.message ?? 'Review discrepancies and try again.',
            });
        },
    });
};
