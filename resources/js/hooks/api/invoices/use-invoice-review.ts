import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceDetail } from '@/types/sourcing';
import { mapInvoiceDetail } from './utils';

type ReviewAction = 'approve' | 'reject' | 'requestChanges';

interface ReviewActionInput {
    invoiceId: number | string;
    note?: string;
}

const ACTION_ENDPOINT: Record<ReviewAction, string> = {
    approve: 'approve',
    reject: 'reject',
    requestChanges: 'request-changes',
};

const ACTION_SUCCESS_COPY: Record<ReviewAction, { title: string; description: string }> = {
    approve: {
        title: 'Invoice approved',
        description: 'Supplier has been notified and the invoice moved to Approved.',
    },
    reject: {
        title: 'Invoice rejected',
        description: 'Supplier will receive your rejection note.',
    },
    requestChanges: {
        title: 'Feedback shared',
        description: 'Supplier has been asked to update the invoice.',
    },
};

export interface InvoiceReviewResult {
    approve: UseMutationResult<InvoiceDetail, ApiError | Error, ReviewActionInput>;
    reject: UseMutationResult<InvoiceDetail, ApiError | Error, ReviewActionInput>;
    requestChanges: UseMutationResult<InvoiceDetail, ApiError | Error, ReviewActionInput>;
}

export function useInvoiceReview(): InvoiceReviewResult {
    const queryClient = useQueryClient();

    const createMutation = (action: ReviewAction) =>
        useMutation<InvoiceDetail, ApiError | Error, ReviewActionInput>({
            mutationFn: async ({ invoiceId, note }) => {
                const id = String(invoiceId ?? '').trim();
                if (!id) {
                    throw new Error('Invoice id is required to review.');
                }

                const body = note && note.trim().length > 0 ? { note: note.trim() } : {};
                const endpoint = ACTION_ENDPOINT[action];
                const response = await api.post<Record<string, unknown>>(
                    `/invoices/${id}/review/${endpoint}`,
                    body,
                );

                return mapInvoiceDetail(response as unknown as Record<string, unknown>);
            },
            onSuccess: (_invoice, variables) => {
                const { title, description } = ACTION_SUCCESS_COPY[action];
                publishToast({
                    variant: 'success',
                    title,
                    description,
                });

                const invoiceId = variables.invoiceId;

                void queryClient.invalidateQueries({ queryKey: ['invoices'] });
                if (invoiceId !== undefined) {
                    void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.detail(invoiceId) });
                }
            },
            onError: (error) => {
                publishToast({
                    variant: 'destructive',
                    title: 'Review action failed',
                    description: error.message ?? 'Unable to update the invoice. Please retry.',
                });
            },
        });

    return {
        approve: createMutation('approve'),
        reject: createMutation('reject'),
        requestChanges: createMutation('requestChanges'),
    };
}
