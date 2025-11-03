import { useMutation, useQueryClient } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { QuoteSummary } from '@/types/sourcing';

interface QuoteCreateResponse {
    id: number;
    rfq_id: number;
    supplier_id: number;
    supplier?: {
        id: number;
        name: string;
    } | null;
    currency: string;
    unit_price: number;
    min_order_qty: number | null;
    lead_time_days: number;
    note: string | null;
    status: string;
    revision_no: number | null;
    submitted_at: string | null;
    items?: Array<{
        id: number;
        rfq_item_id: number;
        unit_price: number;
        lead_time_days: number;
        note: string | null;
    }>;
    attachments?: Array<{
        id: number;
        filename: string;
        path: string;
        mime: string;
        size_bytes: number;
    }>;
}

export interface SubmitQuoteInputItem {
    rfqItemId: number;
    unitPrice: number;
    leadTimeDays: number;
    note?: string;
}

export interface SubmitQuoteInput {
    supplierId: number;
    currency: string;
    unitPrice: number;
    minOrderQty?: number;
    leadTimeDays: number;
    note?: string;
    items: SubmitQuoteInputItem[];
    attachment?: File | null;
}

const mapQuote = (payload: QuoteCreateResponse): QuoteSummary => ({
    id: payload.id,
    supplierId: payload.supplier_id,
    supplierName: payload.supplier?.name ?? `Supplier #${payload.supplier_id}`,
    currency: payload.currency,
    unitPrice: payload.unit_price,
    minOrderQty: payload.min_order_qty ?? undefined,
    leadTimeDays: payload.lead_time_days,
    status: payload.status,
    revision: payload.revision_no ?? 1,
    submittedAt: payload.submitted_at ?? '',
    note: payload.note ?? undefined,
    items: (payload.items ?? []).map((item) => ({
        id: item.id,
        rfqItemId: item.rfq_item_id,
        unitPrice: item.unit_price,
        leadTimeDays: item.lead_time_days,
        note: item.note ?? undefined,
    })),
    attachments: (payload.attachments ?? []).map((attachment) => ({
        id: attachment.id,
        filename: attachment.filename,
        path: attachment.path,
        mime: attachment.mime,
        sizeBytes: attachment.size_bytes,
    })),
});

export function useSubmitQuote(rfqId: number) {
    const queryClient = useQueryClient();

    return useMutation<QuoteSummary, ApiError, SubmitQuoteInput>({
        mutationFn: async (input: SubmitQuoteInput) => {
            const formData = new FormData();

            formData.append('rfq_id', String(rfqId));
            formData.append('supplier_id', String(input.supplierId));
            formData.append('currency', input.currency);
            formData.append('unit_price', String(input.unitPrice));
            formData.append('lead_time_days', String(input.leadTimeDays));

            if (input.minOrderQty) {
                formData.append('min_order_qty', String(input.minOrderQty));
            }

            if (input.note) {
                formData.append('note', input.note);
            }

            input.items.forEach((item, index) => {
                formData.append(`items[${index}][rfq_item_id]`, String(item.rfqItemId));
                formData.append(`items[${index}][unit_price]`, String(item.unitPrice));
                formData.append(`items[${index}][lead_time_days]`, String(item.leadTimeDays));

                if (item.note) {
                    formData.append(`items[${index}][note]`, item.note);
                }
            });

            if (input.attachment) {
                formData.append('attachment', input.attachment);
            }

            const response = (await api.post<QuoteCreateResponse>(
                `/rfqs/${rfqId}/quotes`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                },
            )) as unknown as QuoteCreateResponse;

            return mapQuote(response);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.quotes(rfqId) });
        },
    });
}
