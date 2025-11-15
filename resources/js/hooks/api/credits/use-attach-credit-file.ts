import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { CreditNoteDetail } from '@/types/sourcing';
import { CreditApi, HttpError } from '@/sdk';

import { mapCreditNoteDetail } from './utils';

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

export interface AttachCreditFileInput {
    creditNoteId: number;
    file: File | Blob;
    filename?: string;
}

export function useAttachCreditFile(): UseMutationResult<CreditNoteDetail, HttpError, AttachCreditFileInput> {
    const creditApi = useSdkClient(CreditApi);
    const queryClient = useQueryClient();

    return useMutation<CreditNoteDetail, HttpError, AttachCreditFileInput>({
        mutationFn: async ({ creditNoteId, file, filename }) => {
            const response = (await creditApi.attachCreditNoteFile({ creditNoteId, file, filename })) as Record<string, unknown>;
            const creditPayload = isRecord(response.credit_note) ? response.credit_note : response;

            return mapCreditNoteDetail(creditPayload);
        },
        onSuccess: (creditNote) => {
            publishToast({
                variant: 'success',
                title: 'Attachment uploaded',
                description: 'Your file was added to the credit note.',
            });

            if (creditNote.id) {
                void queryClient.invalidateQueries({ queryKey: queryKeys.credits.detail(creditNote.id) });
            }

            void queryClient.invalidateQueries({ queryKey: queryKeys.credits.list() });
        },
    });
}
