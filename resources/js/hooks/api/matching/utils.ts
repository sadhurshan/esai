import type {
    GoodsReceiptNoteSummary,
    MatchCandidate,
    MatchCandidateLine,
    MatchDiscrepancy,
} from '@/types/sourcing';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const randomId = (prefix: string) => {
    try {
        if (
            typeof crypto !== 'undefined' &&
            typeof crypto.randomUUID === 'function'
        ) {
            return crypto.randomUUID();
        }
    } catch {
        // Ignore runtime issues and fall back to Math.random
    }

    return `${prefix}-${Math.random().toString(36).slice(2, 10)}`;
};

const toNumber = (value: unknown): number | undefined => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        if (Number.isFinite(parsed)) {
            return parsed;
        }
    }

    return undefined;
};

const toStringValue = (value: unknown): string | undefined => {
    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' && Number.isFinite(value)) {
        return `${value}`;
    }

    return undefined;
};

const toDateString = (value: unknown): string | undefined => {
    if (typeof value === 'string') {
        return value;
    }

    if (value instanceof Date) {
        return value.toISOString();
    }

    return undefined;
};

const pick = (payload: Record<string, unknown>, ...keys: string[]) => {
    for (const key of keys) {
        if (payload[key] !== undefined) {
            return payload[key];
        }
    }
    return undefined;
};

const mapGrnSummary = (
    payload: Record<string, unknown>,
): GoodsReceiptNoteSummary => {
    const id = toNumber(pick(payload, 'id', 'grn_id')) ?? 0;
    const grnNumber =
        toStringValue(pick(payload, 'grn_number', 'number', 'reference')) ??
        `GRN-${id}`;

    return {
        id,
        grnNumber,
        purchaseOrderId:
            toNumber(pick(payload, 'po_id', 'purchase_order_id')) ?? 0,
        purchaseOrderNumber: toStringValue(
            pick(payload, 'po_number', 'purchase_order_number'),
        ),
        supplierId:
            toNumber(pick(payload, 'supplier_id', 'supplierId')) ?? undefined,
        supplierName: toStringValue(
            pick(payload, 'supplier_name', 'supplierName'),
        ),
        status:
            (pick(payload, 'status') as GoodsReceiptNoteSummary['status']) ??
            'draft',
        receivedAt: toDateString(pick(payload, 'received_at', 'receivedAt')),
        postedAt: toDateString(pick(payload, 'posted_at', 'postedAt')),
    };
};

export const mapMatchDiscrepancy = (
    payload: Record<string, unknown>,
): MatchDiscrepancy => ({
    id: toStringValue(pick(payload, 'id', 'key')) ?? randomId('disc'),
    type: (toStringValue(pick(payload, 'type')) ??
        'qty') as MatchDiscrepancy['type'],
    label: toStringValue(pick(payload, 'label', 'title')) ?? 'Variance',
    severity: (toStringValue(pick(payload, 'severity')) ??
        'warning') as MatchDiscrepancy['severity'],
    difference: toNumber(pick(payload, 'difference', 'delta', 'qtyVariance')),
    unit: toStringValue(pick(payload, 'unit', 'uom')) ?? null,
    amountMinor: toNumber(
        pick(payload, 'amount_minor', 'amountMinor', 'varianceMinor'),
    ),
    currency: toStringValue(pick(payload, 'currency')),
    notes: toStringValue(pick(payload, 'notes', 'note')),
});

export const mapMatchCandidateLine = (
    payload: Record<string, unknown>,
): MatchCandidateLine => {
    const discrepanciesSource = pick(payload, 'discrepancies', 'variances');
    const discrepancies = Array.isArray(discrepanciesSource)
        ? discrepanciesSource
              .filter(isRecord)
              .map((entry) => mapMatchDiscrepancy(entry))
        : [];

    return {
        id: toStringValue(pick(payload, 'id', 'line_id')) ?? randomId('line'),
        poLineId: toNumber(pick(payload, 'po_line_id', 'poLineId')) ?? 0,
        lineNo: toNumber(pick(payload, 'line_no', 'lineNo')) ?? undefined,
        itemDescription:
            toStringValue(pick(payload, 'item_description', 'description')) ??
            undefined,
        orderedQty: toNumber(pick(payload, 'ordered_qty', 'orderedQty')) ?? 0,
        receivedQty:
            toNumber(pick(payload, 'received_qty', 'receivedQty')) ?? undefined,
        invoicedQty:
            toNumber(pick(payload, 'invoiced_qty', 'invoicedQty')) ?? undefined,
        uom:
            toStringValue(pick(payload, 'uom', 'unit_of_measure')) ?? undefined,
        priceVarianceMinor:
            toNumber(
                pick(payload, 'price_variance_minor', 'priceVarianceMinor'),
            ) ?? undefined,
        qtyVariance:
            toNumber(pick(payload, 'qty_variance', 'qtyVariance')) ?? undefined,
        uomVariance:
            toStringValue(pick(payload, 'uom_variance', 'uomVariance')) ??
            undefined,
        discrepancies,
    };
};

export const mapMatchCandidate = (
    payload: Record<string, unknown>,
): MatchCandidate => {
    const linesSource = pick(payload, 'lines', 'items');
    const lines = Array.isArray(linesSource)
        ? linesSource
              .filter(isRecord)
              .map((item) => mapMatchCandidateLine(item))
        : [];

    const invoicesSource = pick(payload, 'invoices');
    const invoices = Array.isArray(invoicesSource)
        ? invoicesSource.filter(isRecord).map((invoice) => ({
              id: toStringValue(pick(invoice, 'id')) ?? '',
              invoiceNumber:
                  toStringValue(
                      pick(invoice, 'invoice_number', 'invoiceNumber'),
                  ) ?? undefined,
              totalMinor:
                  toNumber(pick(invoice, 'total_minor', 'totalMinor')) ??
                  undefined,
          }))
        : [];

    const grnsSource = pick(payload, 'grns', 'goods_receipts');
    const grns = Array.isArray(grnsSource)
        ? grnsSource.filter(isRecord).map((item) => mapGrnSummary(item))
        : [];

    return {
        id:
            toStringValue(pick(payload, 'id', 'match_id', 'candidate_id')) ??
            randomId('match'),
        purchaseOrderId:
            toNumber(pick(payload, 'po_id', 'purchase_order_id')) ?? 0,
        purchaseOrderNumber:
            toStringValue(
                pick(payload, 'po_number', 'purchase_order_number'),
            ) ?? undefined,
        supplierId:
            toNumber(pick(payload, 'supplier_id', 'supplierId')) ?? undefined,
        supplierName:
            toStringValue(pick(payload, 'supplier_name', 'supplierName')) ??
            undefined,
        currency: toStringValue(pick(payload, 'currency')) ?? undefined,
        poTotalMinor:
            toNumber(pick(payload, 'po_total_minor', 'poTotalMinor')) ??
            undefined,
        receivedTotalMinor:
            toNumber(
                pick(payload, 'received_total_minor', 'receivedTotalMinor'),
            ) ?? undefined,
        invoicedTotalMinor:
            toNumber(
                pick(payload, 'invoiced_total_minor', 'invoicedTotalMinor'),
            ) ?? undefined,
        varianceMinor:
            toNumber(pick(payload, 'variance_minor', 'varianceMinor')) ??
            undefined,
        status: (toStringValue(pick(payload, 'status')) ??
            'pending') as MatchCandidate['status'],
        invoices,
        grns,
        lines,
        lastActivityAt:
            toDateString(pick(payload, 'last_activity_at', 'lastActivityAt')) ??
            undefined,
    };
};
