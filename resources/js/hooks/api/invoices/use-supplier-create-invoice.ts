import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceDetail } from '@/types/sourcing';
import { mapInvoiceDetail } from './utils';

export interface SupplierInvoiceLineInput {
    poLineId: number;
    quantity: number;
    unitPrice: number;
    description?: string;
    uom?: string;
    taxCodeIds?: number[];
}

export interface SupplierCreateInvoiceInput {
    purchaseOrderId: number;
    invoiceNumber: string;
    invoiceDate: string;
    dueDate?: string;
    currency?: string;
    document?: File | null;
    lines: SupplierInvoiceLineInput[];
}

export function useSupplierCreateInvoice(): UseMutationResult<InvoiceDetail, ApiError | Error, SupplierCreateInvoiceInput> {
    const queryClient = useQueryClient();

    return useMutation<InvoiceDetail, ApiError | Error, SupplierCreateInvoiceInput>({
        mutationFn: async ({ purchaseOrderId, invoiceNumber, invoiceDate, dueDate, currency, document, lines }) => {
            if (!Number.isFinite(purchaseOrderId) || purchaseOrderId <= 0) {
                throw new Error('Purchase order required.');
            }

            const trimmedNumber = invoiceNumber.trim();
            if (!trimmedNumber) {
                throw new Error('Invoice number is required.');
            }

            if (!invoiceDate) {
                throw new Error('Invoice date is required.');
            }

            if (!Array.isArray(lines) || lines.length === 0) {
                throw new Error('Add at least one invoice line.');
            }

            const payload = buildFormData({
                invoiceNumber: trimmedNumber,
                invoiceDate,
                dueDate,
                currency,
                document,
                lines,
            });

            const response = await api.post<Record<string, unknown>>(
                `supplier/purchase-orders/${purchaseOrderId}/invoices`,
                payload,
            );

            return mapInvoiceDetail(response as unknown as Record<string, unknown>);
        },
        onSuccess: (invoice) => {
            publishToast({
                variant: 'success',
                title: 'Invoice draft saved',
                description: `Invoice ${invoice.invoiceNumber} is linked to PO ${invoice.purchaseOrder?.poNumber ?? invoice.purchaseOrderId}.`,
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.supplierList() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.invoices.list() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.detail(invoice.purchaseOrderId) });
            void queryClient.setQueryData(queryKeys.invoices.supplierDetail(invoice.id), invoice);
        },
        onError: (error) => {
            const message = error instanceof ApiError ? error.message : error.message ?? 'Unable to save invoice draft.';
            publishToast({
                variant: 'destructive',
                title: 'Invoice draft failed',
                description: message,
            });
        },
    });
}

interface BuildFormDataInput {
    invoiceNumber: string;
    invoiceDate: string;
    dueDate?: string;
    currency?: string;
    document?: File | null;
    lines: SupplierInvoiceLineInput[];
}

function buildFormData({ invoiceNumber, invoiceDate, dueDate, currency, document, lines }: BuildFormDataInput): FormData {
    const formData = new FormData();
    formData.append('invoice_number', invoiceNumber);
    formData.append('invoice_date', invoiceDate);

    if (dueDate) {
        formData.append('due_date', dueDate);
    }

    if (currency && currency.trim().length === 3) {
        formData.append('currency', currency.trim().toUpperCase());
    }

    lines.forEach((line, index) => {
        const prefix = `lines[${index}]`;
        formData.append(`${prefix}[po_line_id]`, String(line.poLineId));
        formData.append(`${prefix}[quantity]`, String(Math.max(1, Math.floor(line.quantity))));
        formData.append(`${prefix}[unit_price]`, line.unitPrice.toString());

        if (line.description) {
            formData.append(`${prefix}[description]`, line.description);
        }

        if (line.uom) {
            formData.append(`${prefix}[uom]`, line.uom);
        }

        if (Array.isArray(line.taxCodeIds)) {
            line.taxCodeIds.forEach((taxCodeId, taxIndex) => {
                if (Number.isFinite(taxCodeId)) {
                    formData.append(`${prefix}[tax_code_ids][${taxIndex}]`, String(taxCodeId));
                }
            });
        }
    });

    if (document instanceof File) {
        formData.append('document', document);
    }

    return formData;
}
