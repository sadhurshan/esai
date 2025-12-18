import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceDetail } from '@/types/sourcing';
import { mapInvoiceDetail } from './utils';

export interface SupplierSubmitInvoiceInput {
    invoiceId: string | number;
    note?: string;
}

export function useSubmitSupplierInvoice(): UseMutationResult<InvoiceDetail, ApiError | Error, SupplierSubmitInvoiceInput> {
    const queryClient = useQueryClient();

    return useMutation<InvoiceDetail, ApiError | Error, SupplierSubmitInvoiceInput>({
        mutationFn: async ({ invoiceId, note }) => {
            const id = String(invoiceId);
            if (!id) {
                throw new Error('Invoice identifier missing.');
            }

            const response = await api.post<Record<string, unknown>>(`supplier/invoices/${id}/submit`, {
                note: note?.trim() || undefined,
            });

            return mapInvoiceDetail(response as unknown as Record<string, unknown>);
        },
        onSuccess: (invoice) => {
            publishToast({
                variant: 'success',
                title: 'Invoice submitted',
                description: `Invoice ${invoice.invoiceNumber} is now in buyer review.`,
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.supplierList() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.list() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.detail(invoice.id) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.supplierDetail(invoice.id) });
        },
        onError: (error) => {
            const message = error instanceof ApiError ? error.message : error.message ?? 'Unable to submit invoice.';
            publishToast({
                variant: 'destructive',
                title: 'Submission failed',
                description: message,
            });
        },
    });
}
