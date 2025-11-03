import { useQuery } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { RFQ, RFQQuote } from '@/types/sourcing';

interface RFQDetailResponse {
    id: number;
    number: string;
    item_name: string;
    type: string;
    quantity: number;
    material: string;
    method: string;
    tolerance: string | null;
    finish: string | null;
    client_company: string;
    status: string;
    deadline_at: string | null;
    sent_at: string | null;
    is_open_bidding: boolean;
    notes: string | null;
    cad_path: string | null;
    quotes: RFQQuoteResponse[];
}

interface RFQQuoteResponse {
    id: number;
    rfq_id: number;
    supplier_id: number;
    supplier_name?: string | null;
    unit_price_usd: number;
    total_price_usd?: number | null;
    lead_time_days: number;
    note: string | null;
    attachment_path: string | null;
    via: string;
    submitted_at: string | null;
    status?: string | null;
    revision?: number | null;
}

export interface RFQDetailResult {
    rfq: RFQ;
    detail: RFQDetailResponse;
    quotes: RFQQuote[];
}

const mapRFQDetail = (payload: RFQDetailResponse): RFQ => ({
    id: payload.id,
    rfqNumber: payload.number,
    title: payload.item_name,
    method: payload.method,
    material: payload.material,
    quantity: payload.quantity,
    dueDate: payload.deadline_at ?? '',
    status: payload.status,
    companyName: payload.client_company,
    openBidding: Boolean(payload.is_open_bidding),
});

export function useRFQ(id: number) {
    return useQuery<RFQDetailResult, ApiError>({
        queryKey: queryKeys.rfqs.detail(id),
        enabled: Number.isFinite(id) && id > 0,
        queryFn: async () => {
            const response = (await api.get<RFQDetailResponse>(`/rfqs/${id}`)) as unknown as RFQDetailResponse;
            const quotes: RFQQuote[] = (response.quotes ?? []).map((quote, index) => ({
                id: quote.id,
                supplierName: quote.supplier_name ?? `Supplier #${quote.supplier_id}`,
                revision: quote.revision ?? index + 1,
                totalPriceUsd: quote.total_price_usd ?? quote.unit_price_usd,
                unitPriceUsd: quote.unit_price_usd,
                leadTimeDays: quote.lead_time_days,
                status: (quote.status ?? 'submitted').replace(/\b\w/g, (char) => char.toUpperCase()),
                submittedAt: quote.submitted_at ?? '',
            }));

            return {
                rfq: mapRFQDetail(response),
                detail: response,
                quotes,
            };
        },
        staleTime: 30_000,
    });
}
