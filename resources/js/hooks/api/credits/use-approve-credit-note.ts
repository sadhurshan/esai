import { useMutation, useQueryClient, type QueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { CreditNoteDetail } from '@/types/sourcing';
import { CreditApi, HttpError } from '@/sdk';

import { mapCreditNoteDetail } from './utils';

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

export interface ApproveCreditNoteInput {
    creditNoteId: number | string;
    decision?: 'approve' | 'reject';
    comment?: string;
}

const DEFAULT_DECISION: ApproveCreditNoteInput['decision'] = 'approve';

export function useApproveCreditNote(): UseMutationResult<CreditNoteDetail, HttpError | Error, ApproveCreditNoteInput> {
    const creditApi = useSdkClient(CreditApi);
    const queryClient = useQueryClient();

    return useMutation<CreditNoteDetail, HttpError | Error, ApproveCreditNoteInput>({
        mutationFn: async ({ creditNoteId, decision, comment }) => {
            const parsedId = Number(creditNoteId);
            if (!Number.isFinite(parsedId) || parsedId <= 0) {
                throw new Error('A valid credit note ID is required.');
            }

            const finalDecision = (decision ?? DEFAULT_DECISION) as 'approve' | 'reject';

            const response = (await creditApi.approveCreditNote({
                creditNoteId: parsedId,
                decision: finalDecision,
                comment,
            })) as Record<string, unknown>;
            const payload = isRecord(response) ? response : {};

            return mapCreditNoteDetail(payload);
        },
        onSuccess: (creditNote, variables) => {
            const approved = (variables.decision ?? DEFAULT_DECISION) === 'approve';

            publishToast({
                variant: approved ? 'success' : 'destructive',
                title: approved ? 'Credit note approved' : 'Credit note rejected',
                description: approved
                    ? `Credit ${creditNote.creditNumber} is now applied to the invoice.`
                    : `Credit ${creditNote.creditNumber} was rejected.`,
            });

            invalidateCreditQueries(queryClient, creditNote);
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to review credit note',
                description: resolveErrorMessage(error),
            });
        },
    });
}

const resolveErrorMessage = (error: unknown): string => {
    if (error instanceof HttpError) {
        const body = (error.body ?? {}) as { message?: string };
        return body?.message ?? error.message ?? 'Try again later.';
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Something went wrong. Try again later.';
};

const invalidateCreditQueries = (queryClient: QueryClient, creditNote: CreditNoteDetail) => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.credits.list() });
    if (creditNote.id) {
        void queryClient.invalidateQueries({ queryKey: queryKeys.credits.detail(creditNote.id) });
    }

    if (creditNote.invoiceId) {
        void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.detail(creditNote.invoiceId) });
    }

    void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.list() });
    void queryClient.invalidateQueries({ queryKey: queryKeys.matching.candidates({}) });
};
