import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    PurchaseOrderChangeOrder,
    PurchaseOrderDelivery,
    PurchaseOrderDetail,
    PurchaseOrderLine,
    PurchaseOrderPdfDocument,
    PurchaseOrderSummary,
} from '@/types/sourcing';
import type {
    PoChangeOrder as SdkPoChangeOrder,
    PurchaseOrder as SdkPurchaseOrder,
    PurchaseOrderLine as SdkPurchaseOrderLine,
    PurchaseOrderPdfDocument as SdkPurchaseOrderPdfDocument,
} from '@/sdk';

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
    company_id: number;
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
    change_orders?: PoChangeOrderResponse[];
    pdf_document_id?: number | null;
    pdf_document?: PurchaseOrderResponsePdfDocument | null;
}

export interface PurchaseOrderResponsePdfDocument {
    id: number;
    filename: string;
    version: number;
    download_url: string;
    created_at?: string | null;
}

export interface PoChangeOrderResponse {
    id: number;
    purchase_order_id: number;
    reason: string;
    status: 'proposed' | 'accepted' | 'rejected';
    changes_json: Record<string, unknown> | null;
    po_revision_no: number | null;
    proposed_by?: {
        id: number | null;
        name: string | null;
        email: string | null;
    } | null;
    created_at: string | null;
    updated_at: string | null;
}

type PurchaseOrderLike = PurchaseOrderResponse | SdkPurchaseOrder;
type PurchaseOrderLineLike = PurchaseOrderResponseLine | SdkPurchaseOrderLine;
type PurchaseOrderChangeOrderLike = PoChangeOrderResponse | SdkPoChangeOrder;
type PurchaseOrderPdfDocumentLike = PurchaseOrderResponsePdfDocument | SdkPurchaseOrderPdfDocument;
type PurchaseOrderDeliveryLike = Partial<PurchaseOrderDelivery> & Record<string, unknown>;

function isSdkPurchaseOrder(payload: PurchaseOrderLike): payload is SdkPurchaseOrder {
    return 'companyId' in payload;
}

function isSdkPurchaseOrderLine(payload: PurchaseOrderLineLike): payload is SdkPurchaseOrderLine {
    return 'lineNo' in payload;
}

function isSdkChangeOrder(payload: PurchaseOrderChangeOrderLike): payload is SdkPoChangeOrder {
    return 'purchaseOrderId' in payload && 'changesJson' in payload;
}

function isSdkPdfDocument(payload: PurchaseOrderPdfDocumentLike): payload is SdkPurchaseOrderPdfDocument {
    return 'downloadUrl' in payload;
}

function toMaybeIsoString(value?: string | null | Date): string | undefined {
    if (!value) {
        return undefined;
    }

    if (value instanceof Date) {
        return value.toISOString();
    }

    return value ?? undefined;
}

const DEFAULT_MINOR_FACTOR = 100;

const toMinorUnits = (value?: number | null): number | undefined => {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return undefined;
    }

    return Math.round(value * DEFAULT_MINOR_FACTOR);
};

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

const pickValue = (payload: Record<string, unknown>, ...keys: string[]): unknown => {
    for (const key of keys) {
        if (key in payload) {
            return payload[key];
        }
    }
    return undefined;
};

const pickString = (payload: Record<string, unknown>, ...keys: string[]): string | undefined => {
    const value = pickValue(payload, ...keys);
    return typeof value === 'string' ? value : undefined;
};

const pickNumber = (payload: Record<string, unknown>, ...keys: string[]): number | undefined => {
    const value = pickValue(payload, ...keys);
    return typeof value === 'number' && Number.isFinite(value) ? value : undefined;
};

const pickDateLike = (payload: Record<string, unknown>, ...keys: string[]): string | Date | undefined => {
    const value = pickValue(payload, ...keys);
    if (typeof value === 'string' || value instanceof Date) {
        return value;
    }
    return undefined;
};

const pickRecord = (payload: Record<string, unknown>, ...keys: string[]): Record<string, unknown> | null | undefined => {
    const value = pickValue(payload, ...keys);
    if (value === null) {
        return null;
    }
    return isRecord(value) ? value : undefined;
};

const toStringArrayOrNull = (value: unknown): string[] | null | undefined => {
    if (value === null) {
        return null;
    }
    if (!Array.isArray(value)) {
        return undefined;
    }
    const entries = value.filter((entry): entry is string => typeof entry === 'string');
    return entries;
};

const readSentBy = (value: unknown): PurchaseOrderDelivery['sentBy'] | undefined => {
    if (!isRecord(value)) {
        return undefined;
    }
    const idValue = value['id'];
    if (typeof idValue !== 'number') {
        return undefined;
    }
    const name = typeof value['name'] === 'string' ? value['name'] : undefined;
    const email = typeof value['email'] === 'string' ? value['email'] : undefined;
    return {
        id: idValue,
        name,
        email,
    };
};

const mapPdfDocument = (
    payload?: PurchaseOrderPdfDocumentLike | null,
): PurchaseOrderPdfDocument | undefined => {
    if (!payload) {
        return undefined;
    }

    if (isSdkPdfDocument(payload)) {
        return {
            id: payload.id,
            filename: payload.filename,
            version: payload.version,
            downloadUrl: payload.downloadUrl,
            createdAt: toMaybeIsoString(payload.createdAt) ?? null,
        };
    }

    return {
        id: payload.id,
        filename: payload.filename,
        version: payload.version,
        downloadUrl: payload.download_url,
        createdAt: payload.created_at ?? null,
    };
};

export const mapPurchaseOrderLine = (payload: PurchaseOrderLineLike): PurchaseOrderLine => {
    const unitPrice = isSdkPurchaseOrderLine(payload)
        ? payload.unitPrice ?? (payload.unitPriceMinor ?? 0) / DEFAULT_MINOR_FACTOR
        : payload.unit_price;

    const deliveryDate = isSdkPurchaseOrderLine(payload)
        ? toMaybeIsoString(payload.deliveryDate) ?? null
        : payload.delivery_date;

    const unitPriceMinor = isSdkPurchaseOrderLine(payload)
        ? payload.unitPriceMinor ?? toMinorUnits(payload.unitPrice)
        : toMinorUnits(payload.unit_price);

    const lineSubtotalMinor = isSdkPurchaseOrderLine(payload)
        ? payload.lineSubtotalMinor ?? (unitPriceMinor ?? 0) * payload.quantity
        : undefined;

    const lineTotalMinor = isSdkPurchaseOrderLine(payload)
        ? payload.lineTotalMinor ?? lineSubtotalMinor ?? (unitPriceMinor ?? 0) * payload.quantity
        : undefined;

    const extra = payload as unknown as Record<string, unknown>;
    const invoicedQuantity = extra?.invoicedQuantity ?? extra?.invoiced_quantity;
    const remainingQuantity = extra?.remainingQuantity ?? extra?.remaining_quantity;
    const receivedQuantity = extra?.receivedQuantity ?? extra?.received_quantity;

    return {
        id: payload.id,
        lineNo: isSdkPurchaseOrderLine(payload) ? payload.lineNo : payload.line_no,
        description: isSdkPurchaseOrderLine(payload) ? payload.description ?? '' : payload.description,
        quantity: payload.quantity,
        uom: isSdkPurchaseOrderLine(payload) ? payload.uom ?? '' : payload.uom,
        unitPrice,
        unitPriceMinor,
        currency: isSdkPurchaseOrderLine(payload) ? payload.currency : undefined,
        lineSubtotalMinor,
        taxTotalMinor: isSdkPurchaseOrderLine(payload) ? payload.taxTotalMinor : undefined,
        lineTotalMinor,
        deliveryDate,
        invoicedQuantity: typeof invoicedQuantity === 'number' ? invoicedQuantity : undefined,
        remainingQuantity: typeof remainingQuantity === 'number' ? remainingQuantity : undefined,
        receivedQuantity: typeof receivedQuantity === 'number' ? receivedQuantity : undefined,
    };
};

export const mapPurchaseOrder = (payload: PurchaseOrderLike): PurchaseOrderSummary => {
    const supplier = isSdkPurchaseOrder(payload) ? payload.supplier : payload.supplier;
    const rfq = isSdkPurchaseOrder(payload) ? payload.rfq : payload.rfq;
    const changeOrdersSource = (isSdkPurchaseOrder(payload)
        ? payload.changeOrders
        : payload.change_orders) as PurchaseOrderChangeOrderLike[] | undefined;
    const linesSource = (payload.lines ?? []) as PurchaseOrderLineLike[];
    const mappedLines = linesSource.map(mapPurchaseOrderLine);
    const pdfDocumentId = isSdkPurchaseOrder(payload) ? payload.pdfDocumentId ?? null : payload.pdf_document_id ?? null;
    const pdfDocumentSource = isSdkPurchaseOrder(payload) ? payload.pdfDocument : payload.pdf_document;
    const mappedPdfDocument = mapPdfDocument(pdfDocumentSource ?? undefined);
    const extra = payload as unknown as Record<string, unknown>;
    const ackStatusRaw = pickString(extra, 'ackStatus', 'ack_status');
    const ackStatus = (ackStatusRaw ?? 'draft') as PurchaseOrderSummary['ackStatus'];
    const sentAt = toMaybeIsoString(pickDateLike(extra, 'sentAt', 'sent_at'));
    const acknowledgedAt = toMaybeIsoString(pickDateLike(extra, 'acknowledgedAt', 'acknowledged_at'));
    const ackReason = pickString(extra, 'ackReason', 'ack_reason');
    const deliveriesSource = pickValue(extra, 'deliveries');
    const deliveries = Array.isArray(deliveriesSource)
        ? (deliveriesSource as PurchaseOrderDeliveryLike[]).map(mapPurchaseOrderDelivery)
        : undefined;
    const latestDeliverySource = pickValue(extra, 'latestDelivery', 'latest_delivery');
    const latestDelivery = latestDeliverySource
        ? mapPurchaseOrderDelivery(latestDeliverySource as PurchaseOrderDeliveryLike)
        : deliveries?.[0];

    const subtotalMinor = isSdkPurchaseOrder(payload) ? payload.subtotalMinor : undefined;
    const taxAmountMinor = isSdkPurchaseOrder(payload) ? payload.taxAmountMinor : undefined;
    let totalMinor = isSdkPurchaseOrder(payload) ? payload.totalMinor : undefined;

    if (totalMinor === undefined && mappedLines.length > 0) {
        totalMinor = mappedLines.reduce((acc, line) => acc + (line.lineTotalMinor ?? (line.unitPriceMinor ?? 0) * line.quantity), 0);
    }

    const derivedSubtotal = mappedLines.reduce(
        (acc, line) => acc + (line.lineSubtotalMinor ?? (line.unitPriceMinor ?? 0) * line.quantity),
        0,
    );
    const derivedTax = mappedLines.reduce((acc, line) => acc + (line.taxTotalMinor ?? 0), 0);

    return {
        id: payload.id,
        companyId: isSdkPurchaseOrder(payload) ? payload.companyId : payload.company_id,
        poNumber: isSdkPurchaseOrder(payload) ? payload.poNumber : payload.po_number,
        status: payload.status,
        ackStatus,
        currency: payload.currency,
        incoterm: (isSdkPurchaseOrder(payload) ? payload.incoterm : payload.incoterm) ?? undefined,
        taxPercent: (isSdkPurchaseOrder(payload) ? payload.taxPercent : payload.tax_percent) ?? undefined,
        revisionNo: isSdkPurchaseOrder(payload) ? payload.revisionNo ?? 0 : payload.revision_no,
        rfqId: isSdkPurchaseOrder(payload) ? payload.rfqId ?? null : payload.rfq_id,
        quoteId: isSdkPurchaseOrder(payload) ? payload.quoteId ?? null : payload.quote_id,
        supplierId: supplier?.id ?? undefined,
        supplierName: supplier?.name ?? undefined,
        rfqNumber: rfq?.number ?? undefined,
        rfqTitle: rfq?.title ?? undefined,
        createdAt: toMaybeIsoString(isSdkPurchaseOrder(payload) ? payload.createdAt : payload.created_at),
        updatedAt: toMaybeIsoString(isSdkPurchaseOrder(payload) ? payload.updatedAt : payload.updated_at),
        sentAt,
        acknowledgedAt,
        ackReason,
        subtotalMinor: subtotalMinor ?? (mappedLines.length ? derivedSubtotal : undefined),
        taxAmountMinor: taxAmountMinor ?? (mappedLines.length ? derivedTax : undefined),
        totalMinor,
        lines: mappedLines,
        changeOrders: (changeOrdersSource ?? []).map(mapChangeOrder),
        pdfDocumentId,
        pdfDocument: mappedPdfDocument ?? (pdfDocumentSource === null ? null : undefined),
        deliveries,
        latestDelivery,
    };
};

export const mapChangeOrder = (
    payload: PurchaseOrderChangeOrderLike,
): PurchaseOrderChangeOrder => ({
    id: payload.id,
    purchaseOrderId: isSdkChangeOrder(payload) ? payload.purchaseOrderId : payload.purchase_order_id,
    reason: payload.reason,
    status: payload.status,
    changes: ((isSdkChangeOrder(payload) ? payload.changesJson : payload.changes_json) ?? {}) as Record<string, unknown>,
    poRevisionNo: (isSdkChangeOrder(payload) ? payload.poRevisionNo : payload.po_revision_no) ?? undefined,
    proposedBy: (() => {
        if (isSdkChangeOrder(payload)) {
            if (!payload.proposedByUser) {
                return undefined;
            }
            return {
                id: payload.proposedByUser.id ?? null,
                name: payload.proposedByUser.name ?? null,
                email: null,
            };
        }

        if (!payload.proposed_by) {
            return undefined;
        }

        return {
            id: payload.proposed_by.id,
            name: payload.proposed_by.name,
            email: payload.proposed_by.email,
        };
    })(),
    createdAt: toMaybeIsoString(
        isSdkChangeOrder(payload) ? payload.proposedAt ?? undefined : payload.created_at,
    ),
    updatedAt: toMaybeIsoString(
        isSdkChangeOrder(payload) ? payload.proposedAt ?? undefined : payload.updated_at,
    ),
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
            changeOrders: (response.change_orders ?? []).map(mapChangeOrder),
        }),
        staleTime: 30_000,
    });
}

const mapPurchaseOrderDelivery = (payload: PurchaseOrderDeliveryLike): PurchaseOrderDelivery => {
    const source = payload as Record<string, unknown>;
    const channel = (payload.channel ?? (pickString(source, 'channel') as PurchaseOrderDelivery['channel']) ?? 'email') as PurchaseOrderDelivery['channel'];
    const status = (payload.status ?? (pickString(source, 'status') as PurchaseOrderDelivery['status']) ?? 'pending') as PurchaseOrderDelivery['status'];
    const responseMeta = payload.responseMeta ?? pickRecord(source, 'response_meta');
    const sentBy = payload.sentBy ?? readSentBy(pickValue(source, 'sent_by')) ?? readSentBy(pickValue(source, 'creator')) ?? null;

    return {
        id: payload.id ?? pickNumber(source, 'id') ?? 0,
        channel,
        status,
        recipientsTo: payload.recipientsTo ?? toStringArrayOrNull(pickValue(source, 'recipients_to')),
        recipientsCc: payload.recipientsCc ?? toStringArrayOrNull(pickValue(source, 'recipients_cc')),
        message: payload.message ?? pickString(source, 'message'),
        deliveryReference: payload.deliveryReference ?? pickString(source, 'deliveryReference', 'delivery_reference'),
        responseMeta,
        errorReason: payload.errorReason ?? pickString(source, 'errorReason', 'error_reason'),
        sentAt: payload.sentAt ?? toMaybeIsoString(pickDateLike(source, 'sentAt', 'sent_at')) ?? null,
        createdAt: payload.createdAt ?? toMaybeIsoString(pickDateLike(source, 'createdAt', 'created_at')) ?? null,
        updatedAt: payload.updatedAt ?? toMaybeIsoString(pickDateLike(source, 'updatedAt', 'updated_at')) ?? null,
        sentBy,
    };
};
