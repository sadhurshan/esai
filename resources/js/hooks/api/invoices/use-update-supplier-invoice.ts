import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';
import { publishToast } from '@/components/ui/use-toast';
import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceDetail } from '@/types/sourcing';
import { mapInvoiceDetail } from './utils';

export interface SupplierUpdateInvoiceLineInput {
    id: number;
    description?: string;
    quantity?: number;
    unitPrice?: number;
    taxCodeIds?: number[];
}

export interface SupplierUpdateInvoiceInput {
    invoiceId: string | number;
    invoiceNumber?: string;
    invoiceDate?: string;
    dueDate?: string | null;
    lines?: SupplierUpdateInvoiceLineInput[];
}

export function useUpdateSupplierInvoice(): UseMutationResult<InvoiceDetail, ApiError | Error, SupplierUpdateInvoiceInput> {
    const queryClient = useQueryClient();

    return useMutation<InvoiceDetail, ApiError | Error, SupplierUpdateInvoiceInput>({
        mutationFn: async ({ invoiceId, invoiceNumber, invoiceDate, dueDate, lines }) => {
            const id = String(invoiceId);

            if (!id) {
                throw new Error('Invoice identifier missing.');
            }

            const payload: Record<string, unknown> = {};

            if (invoiceNumber && invoiceNumber.trim().length > 0) {
                payload.invoice_number = invoiceNumber.trim();
            }

            if (invoiceDate) {
                payload.invoice_date = invoiceDate;
            }

            if (dueDate !== undefined) {
                payload.due_date = dueDate ?? null;
            }

            if (Array.isArray(lines) && lines.length > 0) {
                payload.lines = lines.map((line) => ({
                    id: line.id,
                    description: line.description?.trim() || undefined,
                    quantity: line.quantity,
                    unit_price: line.unitPrice,
                    tax_code_ids: line.taxCodeIds,
                }));
            }

            const response = await api.put<Record<string, unknown>>(`supplier/invoices/${id}`, payload);

            return mapInvoiceDetail(response as unknown as Record<string, unknown>);
        },
        onSuccess: (invoice) => {
            publishToast({
                variant: 'success',
                title: 'Invoice updated',
                description: `Invoice ${invoice.invoiceNumber} has been saved.`,
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.supplierList() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.list() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.detail(invoice.id) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.supplierDetail(invoice.id) });
        },
        onError: (error) => {
            const message = error instanceof ApiError ? error.message : error.message ?? 'Unable to update invoice.';
            publishToast({
                variant: 'destructive',
                title: 'Update failed',
                description: message,
            });
        },
    });
}
