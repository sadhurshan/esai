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

export interface CreateCreditNoteInput {
    invoiceId: number;
    reason: string;
    amount?: number;
    amountMinor?: number;
    purchaseOrderId?: number;
    grnId?: number;
    attachments?: Array<File | Blob | { file: File | Blob; filename?: string }>;
}

interface CreateCreditNoteVariables {
    invoiceId: number;
    reason: string;
    amount?: number | string;
    amountMinor?: number | string;
    purchaseOrderId?: number;
    grnId?: number;
    attachments?: Array<File | Blob | { file: File | Blob; filename?: string }>;
}

export function useCreateCreditNote(): UseMutationResult<
    CreditNoteDetail,
    HttpError | Error,
    CreateCreditNoteInput
> {
    const creditApi = useSdkClient(CreditApi);
    const queryClient = useQueryClient();

    return useMutation<
        CreditNoteDetail,
        HttpError | Error,
        CreateCreditNoteInput
    >({
        mutationFn: async (input) => {
            if (!Number.isFinite(input.invoiceId) || input.invoiceId <= 0) {
                throw new Error(
                    'A valid invoice reference is required to create a credit note.',
                );
            }

            const trimmedReason = input.reason?.trim();
            if (!trimmedReason) {
                throw new Error('A reason for the credit note is required.');
            }

            const payload: CreateCreditNoteVariables = {
                invoiceId: Number(input.invoiceId),
                reason: trimmedReason,
                amount: input.amount,
                amountMinor: input.amountMinor,
                purchaseOrderId: input.purchaseOrderId,
                grnId: input.grnId,
                attachments: input.attachments,
            };

            const response = (await creditApi.createCreditNoteFromInvoice(
                payload,
            )) as Record<string, unknown>;
            const creditPayload = isRecord(response.credit_note)
                ? response.credit_note
                : response;

            return mapCreditNoteDetail(creditPayload);
        },
        onSuccess: (creditNote) => {
            publishToast({
                variant: 'success',
                title: 'Credit note created',
                description: `Credit ${creditNote.creditNumber} was created and is ready for review.`,
            });

            void queryClient.invalidateQueries({
                queryKey: queryKeys.credits.list(),
            });
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
            void queryClient.invalidateQueries({
                queryKey: queryKeys.invoices.list(),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.matching.candidates({}),
            });
        },
        onError: (error) => {
            if (error instanceof HttpError) {
                return;
            }

            publishToast({
                variant: 'destructive',
                title: 'Credit note creation failed',
                description:
                    error.message ?? 'Unable to create the credit note.',
            });
        },
    });
}
