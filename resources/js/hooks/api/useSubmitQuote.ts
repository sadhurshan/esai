import { useMutation, useQueryClient } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { RFQQuote } from '@/types/sourcing';

interface RFQQuoteCreateResponse {
    id: number;
    rfq_id: number;
    supplier_id: number;
    unit_price_usd: number;
    lead_time_days: number;
    note: string | null;
    attachment_path: string | null;
    via: 'direct' | 'bidding';
    submitted_at: string | null;
}

export interface SubmitQuoteInput {
    supplierId: number;
    unitPriceUsd: number;
    leadTimeDays: number;
    note?: string;
    via: 'direct' | 'bidding';
    attachment?: File | null;
}

const mapQuote = (payload: RFQQuoteCreateResponse): RFQQuote => ({
    id: payload.id,
    supplierName: `Supplier #${payload.supplier_id}`,
    revision: 1, // TODO: clarify with spec - API response does not include revision sequence.
    totalPriceUsd: payload.unit_price_usd,
    unitPriceUsd: payload.unit_price_usd,
    leadTimeDays: payload.lead_time_days,
    status: 'Submitted', // TODO: clarify with spec - API response lacks quote status mapping.
    submittedAt: payload.submitted_at ?? '',
});

export function useSubmitQuote(rfqId: number) {
    const queryClient = useQueryClient();

    return useMutation<RFQQuote, ApiError, SubmitQuoteInput>({
        mutationFn: async (input: SubmitQuoteInput) => {
            const formData = new FormData();

            formData.append('supplier_id', String(input.supplierId));
            formData.append('unit_price_usd', String(input.unitPriceUsd));
            formData.append('lead_time_days', String(input.leadTimeDays));
            formData.append('via', input.via);

            if (input.note) {
                formData.append('note', input.note);
            }

            if (input.attachment) {
                formData.append('attachment', input.attachment);
            }

            const response = (await api.post<RFQQuoteCreateResponse>(
                `/rfqs/${rfqId}/quotes`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                },
            )) as unknown as RFQQuoteCreateResponse;

            return mapQuote(response);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.quotes(rfqId) });
        },
    });
}
