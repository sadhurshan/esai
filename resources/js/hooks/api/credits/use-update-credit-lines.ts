import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { CreditApi, HttpError } from '@/sdk';
import type { CreditNoteDetail } from '@/types/sourcing';

import { mapCreditNoteDetail } from './utils';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

export interface UpdateCreditLinesInput {
    creditNoteId: number | string;
    lines: Array<{
        invoiceLineId: number;
        qtyToCredit: number;
        description?: string | null;
        uom?: string | null;
    }>;
}

export function useUpdateCreditLines(): UseMutationResult<
    CreditNoteDetail,
    HttpError | Error,
    UpdateCreditLinesInput
> {
    const creditApi = useSdkClient(CreditApi);
    const queryClient = useQueryClient();

    return useMutation<
        CreditNoteDetail,
        HttpError | Error,
        UpdateCreditLinesInput
    >({
        mutationFn: async ({ creditNoteId, lines }) => {
            const parsedId = Number(creditNoteId);
            if (!Number.isFinite(parsedId) || parsedId <= 0) {
                throw new Error('A valid credit note ID is required.');
            }

            const response = (await creditApi.updateCreditNoteLines({
                creditNoteId: parsedId,
                lines,
            })) as Record<string, unknown>;

            return mapCreditNoteDetail(isRecord(response) ? response : {});
        },
        onSuccess: (creditNote) => {
            publishToast({
                variant: 'success',
                title: 'Credit lines saved',
                description: 'Draft credit note totals were refreshed.',
            });

            if (creditNote.id) {
                void queryClient.invalidateQueries({
                    queryKey: queryKeys.credits.detail(creditNote.id),
                });
            }

            void queryClient.invalidateQueries({
                queryKey: queryKeys.credits.list(),
            });
            if (creditNote.invoiceId) {
                void queryClient.invalidateQueries({
                    queryKey: queryKeys.invoices.detail(creditNote.invoiceId),
                });
            }

            void queryClient.invalidateQueries({
                queryKey: queryKeys.matching.candidates({}),
            });
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to save credit lines',
                description: resolveErrorMessage(error),
            });
        },
    });
}

const resolveErrorMessage = (error: unknown): string => {
    if (error instanceof HttpError) {
        const body = (error.body ?? {}) as { message?: string };
        return body?.message ?? error.message ?? 'Please try again.';
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Please try again.';
};
