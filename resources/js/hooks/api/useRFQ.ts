import { useQuery } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { QuoteSummary, RFQ, RfqItem } from '@/types/sourcing';

export interface RFQDetailResponse {
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
    items?: RfqItemResponse[];
    quotes: QuoteResponse[];
}

interface RfqItemResponse {
    id: number;
    line_no: number;
    part_name: string;
    spec: string | null;
    quantity: number;
    uom: string;
    target_price: number | null;
}

interface QuoteItemResponse {
    id: number;
    rfq_item_id: number;
    unit_price: number;
    lead_time_days: number;
    note: string | null;
}

interface QuoteAttachmentResponse {
    id: number;
    filename: string;
    path: string;
    mime: string;
    size_bytes: number;
}

interface QuoteResponse {
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
    submitted_by: number | null;
    submitted_at: string | null;
    items?: QuoteItemResponse[];
    attachments?: QuoteAttachmentResponse[];
}

export interface RFQDetailResult {
    rfq: RFQ;
    detail: RFQDetailResponse;
    quotes: QuoteSummary[];
}

const mapRfqItem = (item: RfqItemResponse): RfqItem => ({
    id: item.id,
    lineNo: item.line_no,
    partName: item.part_name,
    spec: item.spec,
    quantity: item.quantity,
    uom: item.uom,
    targetPrice: item.target_price ?? undefined,
});

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
    items: (payload.items ?? []).map(mapRfqItem),
});

const mapQuote = (payload: QuoteResponse): QuoteSummary => ({
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
    items: (payload.items ?? []).map((item): QuoteSummary['items'][number] => ({
        id: item.id,
        rfqItemId: item.rfq_item_id,
        unitPrice: item.unit_price,
        leadTimeDays: item.lead_time_days,
        note: item.note ?? undefined,
    })),
    attachments: (payload.attachments ?? []).map((attachment): QuoteSummary['attachments'][number] => ({
        id: attachment.id,
        filename: attachment.filename,
        path: attachment.path,
        mime: attachment.mime,
        sizeBytes: attachment.size_bytes,
    })),
});

export async function fetchRfqDetail(rfqId: number): Promise<RFQDetailResponse> {
    return (await api.get<RFQDetailResponse>(`/rfqs/${rfqId}`)) as unknown as RFQDetailResponse;
}

export function useRFQ(id: number) {
    return useQuery<RFQDetailResponse, ApiError, RFQDetailResult>({
        queryKey: queryKeys.rfqs.detail(id),
        enabled: Number.isFinite(id) && id > 0,
        queryFn: () => fetchRfqDetail(id),
        select: (response) => ({
            rfq: mapRFQDetail(response),
            detail: response,
            quotes: (response.quotes ?? []).map((quote) => mapQuote(quote)),
        }),
        staleTime: 30_000,
    });
}

export { mapQuote, mapRFQDetail, mapRfqItem };
