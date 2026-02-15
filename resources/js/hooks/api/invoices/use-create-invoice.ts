import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { InvoiceDetail } from '@/types/sourcing';
import { mapInvoiceDetail } from './utils';

interface CreateInvoiceLineInput {
    poLineId: number;
    qtyInvoiced: number;
    unitPriceMinor?: number;
}

export interface CreateInvoiceInput {
    poId: number;
    supplierId?: number | null;
    invoiceNumber: string;
    invoiceDate?: string;
    currency: string;
    lines?: CreateInvoiceLineInput[];
}

interface CreateInvoicePayload {
    po_id: number;
    supplier_id?: number;
    invoice_number: string;
    invoice_date?: string;
    currency: string;
    lines?: Array<{
        po_line_id: number;
        qty_invoiced: number;
        unit_price_minor?: number;
    }>;
}

export function useCreateInvoice(): UseMutationResult<
    InvoiceDetail,
    ApiError | Error,
    CreateInvoiceInput
> {
    const { notifyPlanLimit } = useAuth();
    const queryClient = useQueryClient();

    return useMutation<InvoiceDetail, ApiError | Error, CreateInvoiceInput>({
        mutationFn: async ({
            poId,
            supplierId,
            invoiceNumber,
            invoiceDate,
            currency,
            lines,
        }) => {
            if (!Number.isFinite(poId) || poId <= 0) {
                throw new Error(
                    'A valid purchase order is required to create an invoice.',
                );
            }

            const trimmedNumber = invoiceNumber.trim();
            if (trimmedNumber.length === 0) {
                throw new Error('Invoice number is required.');
            }

            if (!currency || currency.trim().length === 0) {
                throw new Error('Currency is required for invoice creation.');
            }

            const payload: CreateInvoicePayload = {
                po_id: poId,
                supplier_id: Number.isFinite(supplierId ?? NaN)
                    ? Number(supplierId)
                    : undefined,
                invoice_number: trimmedNumber,
                invoice_date: invoiceDate?.trim() || undefined,
                currency: currency.trim().toUpperCase(),
                lines: lines?.map((line) => ({
                    po_line_id: line.poLineId,
                    qty_invoiced: line.qtyInvoiced,
                    unit_price_minor: line.unitPriceMinor,
                })),
            };

            const response = (await api.post<Record<string, unknown>>(
                '/invoices/from-po',
                payload,
            )) as unknown as Record<string, unknown>;

            return mapInvoiceDetail(response);
        },
        onSuccess: (invoice) => {
            publishToast({
                variant: 'success',
                title: 'Invoice created',
                description: `Invoice ${invoice.invoiceNumber} is now linked to PO ${invoice.purchaseOrder?.poNumber ?? invoice.purchaseOrderId}.`,
            });

            const poId = invoice.purchaseOrderId;
            if (poId) {
                void queryClient.invalidateQueries({
                    queryKey: queryKeys.purchaseOrders.detail(poId),
                });
                void queryClient.invalidateQueries({
                    queryKey: queryKeys.purchaseOrders.events(poId),
                });
            }

            void queryClient.invalidateQueries({
                queryKey: queryKeys.invoices.list(),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.invoices.detail(invoice.id),
            });
        },
        onError: (error) => {
            if (
                error instanceof ApiError &&
                (error.status === 402 || error.status === 403)
            ) {
                notifyPlanLimit({
                    code: 'invoices',
                    message:
                        error.message ?? 'Upgrade required to create invoices.',
                });
            }

            const message =
                error instanceof ApiError
                    ? error.message
                    : (error.message ?? 'Unable to create invoice.');

            publishToast({
                variant: 'destructive',
                title: 'Invoice creation failed',
                description: message,
            });
        },
    });
}
