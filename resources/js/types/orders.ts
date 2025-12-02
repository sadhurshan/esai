export type SalesOrderStatus =
    | 'draft'
    | 'pending_ack'
    | 'accepted'
    | 'partially_fulfilled'
    | 'fulfilled'
    | 'cancelled';

export type ShipmentStatus = 'pending' | 'in_transit' | 'delivered' | 'cancelled';

export interface OrderShippingProfile {
    shipToName?: string | null;
    shipToAddress?: string | null;
    incoterm?: string | null;
    carrierPreference?: string | null;
    instructions?: string | null;
}

export interface SalesOrderTotals {
    currency: string;
    subtotalMinor?: number | null;
    taxMinor?: number | null;
    totalMinor?: number | null;
}

export interface FulfillmentSummary {
    orderedQty: number;
    shippedQty: number;
    percent: number;
    updatedAt?: string | null;
}

export interface SalesOrderLine {
    id: number;
    soLineId?: number;
    poLineId?: number;
    itemId?: number | null;
    sku?: string | null;
    description: string;
    uom?: string | null;
    qtyOrdered: number;
    qtyAllocated?: number | null;
    qtyShipped?: number | null;
    unitPriceMinor?: number | null;
    currency?: string;
}

export interface ShipmentLineItem {
    soLineId: number;
    qtyShipped: number;
}

export interface SalesOrderShipment {
    id: number;
    soId: number;
    shipmentNo: string;
    status: ShipmentStatus;
    carrier?: string | null;
    trackingNumber?: string | null;
    shippedAt?: string | null;
    deliveredAt?: string | null;
    lines: ShipmentLineItem[];
    documents?: Array<{ id: number; filename: string; url?: string | null }>;
}

export interface AcknowledgementRecord {
    decision: 'accept' | 'decline';
    reason?: string | null;
    acknowledgedAt?: string | null;
    actorName?: string | null;
}

export interface SalesOrderTimelineEntry {
    id: string | number;
    type: 'acknowledged' | 'shipment' | 'status_change' | 'note';
    summary: string;
    description?: string | null;
    occurredAt?: string | null;
    actor?: {
        id?: number | null;
        name?: string | null;
        email?: string | null;
    };
    metadata?: {
        status?: string | null;
        [key: string]: unknown;
    } | null;
}

export interface SalesOrderSummary {
    id: number;
    soNumber: string;
    poId: number;
    buyerCompanyId: number;
    buyerCompanyName?: string | null;
    supplierCompanyId: number;
    supplierCompanyName?: string | null;
    status: SalesOrderStatus;
    currency: string;
    totals?: SalesOrderTotals;
    issueDate?: string | null;
    dueDate?: string | null;
    notes?: string | null;
    shipping?: OrderShippingProfile | null;
    fulfillment?: FulfillmentSummary | null;
    shipmentsCount?: number | null;
    lastEventAt?: string | null;
}

export interface SalesOrderDetail extends SalesOrderSummary {
    lines: SalesOrderLine[];
    shipments: SalesOrderShipment[];
    timeline: SalesOrderTimelineEntry[];
    acknowledgements?: AcknowledgementRecord[];
}

export interface CursorMeta {
    nextCursor?: string | null;
    prevCursor?: string | null;
    perPage?: number;
    raw?: Record<string, unknown> | null;
}

export interface CursorPaginated<T> {
    items: T[];
    meta?: CursorMeta;
}

export interface SupplierOrderFilters {
    cursor?: string | null;
    perPage?: number;
    status?: SalesOrderStatus | SalesOrderStatus[];
    buyerCompanyId?: number;
    dateFrom?: string;
    dateTo?: string;
    search?: string;
}

export interface BuyerOrderFilters {
    cursor?: string | null;
    perPage?: number;
    supplierCompanyId?: number;
    status?: SalesOrderStatus | SalesOrderStatus[];
    dateFrom?: string;
    dateTo?: string;
    search?: string;
}

export interface AckOrderPayload {
    decision: 'accept' | 'decline';
    reason?: string | null;
}

export interface CreateShipmentPayload {
    carrier: string;
    trackingNumber: string;
    shippedAt: string;
    lines: ShipmentLineItem[];
    notes?: string | null;
}

export interface UpdateShipmentStatusPayload {
    status: Extract<ShipmentStatus, 'in_transit' | 'delivered'>;
    deliveredAt?: string | null;
}
