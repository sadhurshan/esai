import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderDetail, PurchaseOrderLine, PurchaseOrderSummary } from '@/types/sourcing';

export interface PurchaseOrderResponseLine {
    id: number;
    line_no: number;
    description: string;
    quantity: number;
    uom: string;
    unit_price: number;
    delivery_date: string | null;
}

export interface PurchaseOrderResponse {
    id: number;
    po_number: string;
    status: string;
    currency: string;
    incoterm: string | null;
    tax_percent: number | null;
    revision_no: number;
    rfq_id: number | null;
    quote_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    supplier?: {
        id: number | null;
        name: string | null;
    } | null;
    rfq?: {
        id: number | null;
        number: string | null;
        title: string | null;
    } | null;
    lines?: PurchaseOrderResponseLine[];
}

export const mapPurchaseOrderLine = (
    payload: PurchaseOrderResponseLine,
): PurchaseOrderLine => ({
    id: payload.id,
    lineNo: payload.line_no,
    description: payload.description,
    quantity: payload.quantity,
    uom: payload.uom,
    unitPrice: payload.unit_price,
    deliveryDate: payload.delivery_date,
});

export const mapPurchaseOrder = (
    payload: PurchaseOrderResponse,
): PurchaseOrderSummary => ({
    id: payload.id,
    poNumber: payload.po_number,
    status: payload.status,
    currency: payload.currency,
    incoterm: payload.incoterm ?? undefined,
    taxPercent: payload.tax_percent ?? undefined,
    revisionNo: payload.revision_no,
    rfqId: payload.rfq_id,
    quoteId: payload.quote_id,
    supplierId: payload.supplier?.id ?? undefined,
    supplierName: payload.supplier?.name ?? undefined,
    rfqNumber: payload.rfq?.number ?? undefined,
    rfqTitle: payload.rfq?.title ?? undefined,
    createdAt: payload.created_at ?? undefined,
    updatedAt: payload.updated_at ?? undefined,
    lines: (payload.lines ?? []).map(mapPurchaseOrderLine),
});

export function usePurchaseOrder(
    id: number,
): UseQueryResult<PurchaseOrderDetail, ApiError> {
    return useQuery<PurchaseOrderResponse, ApiError, PurchaseOrderDetail>({
        queryKey: queryKeys.purchaseOrders.detail(id),
        enabled: Number.isFinite(id) && id > 0,
        queryFn: async () =>
            (await api.get<PurchaseOrderResponse>(
                `/purchase-orders/${id}`,
            )) as unknown as PurchaseOrderResponse,
        select: (response) => ({
            ...mapPurchaseOrder(response),
            lines: (response.lines ?? []).map(mapPurchaseOrderLine),
        }),
        staleTime: 30_000,
    });
}
