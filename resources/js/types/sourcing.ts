export type Paged<T> = {
    items: T[];
    meta: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
};

export interface Supplier {
    id: number;
    name: string;
    rating: number;
    capabilities: string[];
    materials: string[];
    locationRegion: string;
    minimumOrderQuantity: number;
    averageResponseHours: number;
}

export interface RfqItem {
    id: number;
    lineNo: number;
    partName: string;
    spec?: string | null;
    quantity: number;
    uom: string;
    targetPrice?: number | null;
}

export interface RfqInvitation {
    id: number;
    status: 'invited' | 'accepted' | 'declined';
    invitedAt?: string | null;
    supplier: {
        id: number;
        name: string;
        ratingAvg?: number | null;
    } | null;
}

export interface RFQ {
    id: number;
    rfqNumber: string;
    title: string;
    method: string;
    material: string;
    quantity: number;
    dueDate: string;
    status: string;
    companyName: string;
    openBidding: boolean;
    items: RfqItem[];
}

export interface QuoteAttachment {
    id: number;
    filename: string;
    path: string;
    mime: string;
    sizeBytes: number;
}

export interface QuoteLineItem {
    id: number;
    rfqItemId: number;
    unitPrice: number;
    leadTimeDays: number;
    note?: string | null;
}

export interface QuoteSummary {
    id: number;
    supplierId: number;
    supplierName: string;
    currency: string;
    unitPrice: number;
    minOrderQty?: number | null;
    leadTimeDays: number;
    status: string;
    revision: number;
    submittedAt: string;
    note?: string | null;
    items: QuoteLineItem[];
    attachments: QuoteAttachment[];
}

export interface Order {
    id: number;
    orderNumber: string;
    party: string;
    item: string;
    quantity: number;
    totalUsd: number;
    orderDate: string;
    status: string;
}

export interface PurchaseOrderLine {
    id: number;
    lineNo: number;
    description: string;
    quantity: number;
    uom: string;
    unitPrice: number;
    deliveryDate?: string | null;
}

export interface PurchaseOrderSummary {
    id: number;
    poNumber: string;
    status: string;
    currency: string;
    incoterm?: string | null;
    revisionNo: number;
    rfqId: number | null;
    quoteId: number | null;
    supplierName?: string | null;
    createdAt?: string | null;
    lines?: PurchaseOrderLine[];
}
