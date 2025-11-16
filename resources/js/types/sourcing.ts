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
    companyId: number;
    name: string;
    status: 'pending' | 'approved' | 'rejected' | 'suspended';
    capabilities: SupplierCapabilities;
    ratingAvg: number;
    riskGrade?: string | null;
    contact: {
        email?: string | null;
        phone?: string | null;
        website?: string | null;
    };
    address: {
        line1?: string | null;
        city?: string | null;
        country?: string | null;
    };
    geo: {
        lat?: number | null;
        lng?: number | null;
    };
    leadTimeDays?: number | null;
    moq?: number | null;
    verifiedAt?: string | null;
    createdAt?: string | null;
    updatedAt?: string | null;
    branding?: SupplierBranding | null;
    certificates: SupplierCertificateSummary;
    company?: SupplierCompanySummary | null;
    documents?: SupplierDocument[] | null;
}

export interface SupplierCapabilities {
    methods?: string[];
    materials?: string[];
    tolerances?: string[];
    finishes?: string[];
    industries?: string[];
    [key: string]: unknown;
}

export interface SupplierBranding {
    logoUrl?: string | null;
    markUrl?: string | null;
}

export interface SupplierCertificateSummary {
    valid: number;
    expiring: number;
    expired: number;
}

export interface SupplierCompanySummary {
    id: number;
    name: string;
    website?: string | null;
    country?: string | null;
    supplierStatus?: string | null;
    isVerified?: boolean | null;
}

export type SupplierDocumentType =
    | 'iso9001'
    | 'iso14001'
    | 'as9100'
    | 'itar'
    | 'reach'
    | 'rohs'
    | 'insurance'
    | 'nda'
    | 'other';

export type SupplierDocumentStatus = 'valid' | 'expiring' | 'expired';

export interface SupplierDocument {
    id: number;
    supplierId: number;
    companyId: number;
    type: SupplierDocumentType;
    status: SupplierDocumentStatus;
    path: string;
    mime: string;
    sizeBytes: number;
    issuedAt?: string | null;
    expiresAt?: string | null;
    createdAt?: string | null;
    updatedAt?: string | null;
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

export interface DocumentAttachment {
    id: number;
    filename: string;
    mime: string;
    sizeBytes: number;
    createdAt?: string | null;
    downloadUrl?: string | null;
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
    unitPriceMinor?: number;
    currency?: string;
    lineSubtotalMinor?: number;
    taxTotalMinor?: number;
    lineTotalMinor?: number;
    deliveryDate?: string | null;
    invoicedQuantity?: number | null;
    remainingQuantity?: number | null;
    receivedQuantity?: number | null;
}

export interface PurchaseOrderChangeOrder {
    id: number;
    purchaseOrderId: number;
    reason: string;
    status: 'proposed' | 'accepted' | 'rejected';
    changes: Record<string, unknown>;
    poRevisionNo?: number | null;
    proposedBy?: {
        id: number | null;
        name: string | null;
        email: string | null;
    } | null;
    createdAt?: string | null;
    updatedAt?: string | null;
}

export interface PurchaseOrderPdfDocument {
    id: number;
    filename: string;
    version: number;
    downloadUrl: string;
    createdAt?: string | null;
}

export interface PurchaseOrderSummary {
    id: number;
    companyId: number;
    poNumber: string;
    status: string;
    ackStatus?: 'draft' | 'sent' | 'acknowledged' | 'declined';
    currency: string;
    incoterm?: string | null;
    taxPercent?: number | null;
    revisionNo: number;
    rfqId: number | null;
    quoteId: number | null;
    supplierId?: number | null;
    supplierName?: string | null;
    rfqNumber?: string | null;
    rfqTitle?: string | null;
    createdAt?: string | null;
    updatedAt?: string | null;
    sentAt?: string | null;
    acknowledgedAt?: string | null;
    ackReason?: string | null;
    subtotalMinor?: number;
    taxAmountMinor?: number;
    totalMinor?: number;
    lines?: PurchaseOrderLine[];
    changeOrders?: PurchaseOrderChangeOrder[];
    pdfDocumentId?: number | null;
    pdfDocument?: PurchaseOrderPdfDocument | null;
    latestDelivery?: PurchaseOrderDelivery | null;
    deliveries?: PurchaseOrderDelivery[];
}

export interface PurchaseOrderDetail extends PurchaseOrderSummary {
    lines: PurchaseOrderLine[];
    changeOrders: PurchaseOrderChangeOrder[];
}

export interface PurchaseOrderDelivery {
    id: number;
    channel: 'email' | 'webhook';
    status: 'pending' | 'sent' | 'failed';
    recipientsTo?: string[] | null;
    recipientsCc?: string[] | null;
    message?: string | null;
    deliveryReference?: string | null;
    responseMeta?: Record<string, unknown> | null;
    errorReason?: string | null;
    sentAt?: string | null;
    createdAt?: string | null;
    updatedAt?: string | null;
    sentBy?: {
        id: number;
        name?: string | null;
        email?: string | null;
    } | null;
}

export interface PurchaseOrderEvent {
    id: number;
    purchaseOrderId: number;
    type: string;
    summary: string;
    description?: string | null;
    metadata?: Record<string, unknown> | null;
    actor?: {
        id?: number | null;
        name?: string | null;
        email?: string | null;
        type?: string | null;
    } | null;
    occurredAt?: string | null;
    createdAt?: string | null;
}

export interface InvoiceSummary {
    id: string;
    companyId: number;
    purchaseOrderId: number;
    supplierId: number;
    invoiceNumber: string;
    invoiceDate?: string | null;
    currency: string;
    status: string;
    subtotal: number;
    taxAmount: number;
    total: number;
    subtotalMinor?: number;
    taxAmountMinor?: number;
    totalMinor?: number;
    supplier?: {
        id: number;
        name?: string | null;
    } | null;
    purchaseOrder?: {
        id: number;
        poNumber?: string | null;
    } | null;
    document?: DocumentAttachment | null;
    attachments?: DocumentAttachment[];
    matchSummary?: Record<string, number>;
    createdAt?: string | null;
    updatedAt?: string | null;
}

export interface InvoiceLineDetail {
    id: number;
    poLineId: number;
    description: string;
    quantity: number;
    uom: string;
    currency?: string;
    unitPrice: number;
    unitPriceMinor?: number;
    taxCodeIds?: number[];
}

export interface InvoiceDetail extends InvoiceSummary {
    lines: InvoiceLineDetail[];
    matches?: unknown[];
}

export interface GrnLine {
    id?: number;
    grnId?: number;
    poLineId: number;
    lineNo?: number;
    description?: string | null;
    orderedQty: number;
    qtyReceived: number;
    previouslyReceived?: number;
    remainingQty?: number;
    uom?: string | null;
    unitPriceMinor?: number;
    currency?: string;
    variance?: 'over' | 'short' | null;
    notes?: string | null;
}

export interface GrnTimelineEntry {
    id: string;
    summary: string;
    occurredAt?: string | null;
    actor?: {
        id?: number | null;
        name?: string | null;
    } | null;
}

export interface GoodsReceiptNoteSummary {
    id: number;
    grnNumber: string;
    purchaseOrderId: number;
    purchaseOrderNumber?: string | null;
    supplierName?: string | null;
    supplierId?: number | null;
    status: 'draft' | 'posted' | string;
    receivedAt?: string | null;
    postedAt?: string | null;
    linesCount?: number;
    attachmentsCount?: number;
    createdBy?: {
        id?: number | null;
        name?: string | null;
    } | null;
}

export interface GoodsReceiptNoteDetail extends GoodsReceiptNoteSummary {
    reference?: string | null;
    notes?: string | null;
    lines: GrnLine[];
    attachments: DocumentAttachment[];
    timeline?: GrnTimelineEntry[];
}

export interface MatchDiscrepancy {
    id: string;
    type: 'qty' | 'price' | 'uom';
    label: string;
    severity?: 'info' | 'warning' | 'critical';
    difference?: number;
    unit?: string | null;
    amountMinor?: number;
    currency?: string;
    notes?: string | null;
}

export interface MatchCandidateLine {
    id: string;
    poLineId: number;
    lineNo?: number;
    itemDescription?: string | null;
    orderedQty: number;
    receivedQty?: number;
    invoicedQty?: number;
    uom?: string | null;
    priceVarianceMinor?: number;
    qtyVariance?: number;
    uomVariance?: string | null;
    discrepancies: MatchDiscrepancy[];
}

export interface MatchCandidate {
    id: string;
    purchaseOrderId: number;
    purchaseOrderNumber?: string | null;
    supplierId?: number | null;
    supplierName?: string | null;
    currency?: string;
    poTotalMinor?: number;
    receivedTotalMinor?: number;
    invoicedTotalMinor?: number;
    varianceMinor?: number;
    status: 'clean' | 'variance' | 'pending' | 'resolved';
    invoices?: Array<{ id: string; invoiceNumber?: string | null; totalMinor?: number }>;
    grns?: GoodsReceiptNoteSummary[];
    lines: MatchCandidateLine[];
    lastActivityAt?: string | null;
}

export interface MatchResolutionInput {
    invoiceId: string;
    purchaseOrderId: number;
    grnIds?: number[];
    decisions: Array<{
        lineId: string;
        type: MatchDiscrepancy['type'];
        status: 'accept' | 'reject' | 'credit' | 'pending';
        notes?: string;
    }>;
}

export interface CreditNoteLine {
    id?: string;
    invoiceLineId: number;
    description?: string | null;
    qtyInvoiced?: number;
    qtyAlreadyCredited?: number;
    qtyToCredit: number;
    unitPriceMinor: number;
    currency: string;
    totalMinor?: number;
    uom?: string | null;
}

export interface CreditNoteSummary {
    id: string;
    creditNumber: string;
    status: 'draft' | 'pending_review' | 'issued' | 'approved' | 'rejected' | string;
    supplierName?: string | null;
    supplierId?: number | null;
    invoiceId?: number;
    invoiceNumber?: string | null;
    currency?: string;
    totalMinor?: number;
    createdAt?: string | null;
    issuedAt?: string | null;
}

export interface CreditNoteDetail extends CreditNoteSummary {
    reason?: string | null;
    lines: CreditNoteLine[];
    attachments: DocumentAttachment[];
    balanceMinor?: number;
    notes?: string | null;
    invoice?: InvoiceSummary | null;
    purchaseOrder?: PurchaseOrderSummary | null;
    goodsReceiptNote?: GoodsReceiptNoteSummary | null;
}
