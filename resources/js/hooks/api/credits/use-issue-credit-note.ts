import {
    useMutation,
    useQueryClient,
    type QueryClient,
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

export interface IssueCreditNoteInput {
    creditNoteId: number | string;
}

export function useIssueCreditNote(): UseMutationResult<
    CreditNoteDetail,
    HttpError | Error,
    IssueCreditNoteInput
> {
    const creditApi = useSdkClient(CreditApi);
    const queryClient = useQueryClient();

    return useMutation<
        CreditNoteDetail,
        HttpError | Error,
        IssueCreditNoteInput
    >({
        mutationFn: async ({ creditNoteId }) => {
            const parsedId = Number(creditNoteId);
            if (!Number.isFinite(parsedId) || parsedId <= 0) {
                throw new Error('A valid credit note ID is required.');
            }

            const response = (await creditApi.issueCreditNote({
                creditNoteId: parsedId,
            })) as Record<string, unknown>;
            const payload = isRecord(response) ? response : {};

            return mapCreditNoteDetail(payload);
        },
        onSuccess: (creditNote) => {
            publishToast({
                variant: 'success',
                title: 'Credit note issued',
                description: `Credit ${creditNote.creditNumber} was sent for approval.`,
            });

            invalidateCreditQueries(queryClient, creditNote);
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to post credit note',
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

const invalidateCreditQueries = (
    queryClient: QueryClient,
    creditNote: CreditNoteDetail,
) => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.credits.list() });
    if (creditNote.id) {
        void queryClient.invalidateQueries({
            queryKey: queryKeys.credits.detail(creditNote.id),
        });
    }

    if (creditNote.invoiceId) {
        void queryClient.invalidateQueries({
            queryKey: queryKeys.invoices.detail(creditNote.invoiceId),
        });
    }

    void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.list() });
    void queryClient.invalidateQueries({
        queryKey: queryKeys.matching.candidates({}),
    });
};
