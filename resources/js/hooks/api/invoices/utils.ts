import type { Invoice } from '@/sdk';
import type { DocumentAttachment, InvoiceDetail, InvoiceLineDetail, InvoiceSummary } from '@/types/sourcing';

type InvoiceSummaryLike = Record<string, unknown>;
type InvoiceLike = (Partial<Invoice> & Record<string, unknown>) | Record<string, unknown>;
type InvoiceLineLike = Record<string, unknown>;

const DEFAULT_MINOR_FACTOR = 100;

const toNumber = (value: unknown): number | undefined => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim().length > 0) {
        const parsed = Number(value);
        if (!Number.isNaN(parsed)) {
            return parsed;
        }
    }

    return undefined;
};

const readNumber = (payload: Record<string, unknown>, ...keys: string[]): number | undefined => {
    for (const key of keys) {
        if (key in payload) {
            const value = toNumber(payload[key]);
            if (value !== undefined) {
                return value;
            }
        }
    }
    return undefined;
};

const readString = (payload: Record<string, unknown>, ...keys: string[]): string | undefined => {
    for (const key of keys) {
        const value = payload[key];
        if (typeof value === 'string' && value.length > 0) {
            return value;
        }
    }
    return undefined;
};

const toMinorUnits = (major?: number): number | undefined => {
    if (typeof major !== 'number' || Number.isNaN(major)) {
        return undefined;
    }
    return Math.round(major * DEFAULT_MINOR_FACTOR);
};

export function mapInvoiceDocument(payload?: Record<string, unknown> | null): DocumentAttachment | null {
    if (!payload) {
        return null;
    }

    return {
        id: readNumber(payload, 'id') ?? 0,
        filename: readString(payload, 'filename') ?? 'document.pdf',
        mime: readString(payload, 'mime') ?? 'application/pdf',
        sizeBytes: readNumber(payload, 'sizeBytes', 'size_bytes') ?? 0,
        createdAt: readString(payload, 'createdAt', 'created_at') ?? null,
    };
};

export function mapInvoiceSummary(payload: InvoiceSummaryLike): InvoiceSummary {
    const source = payload as Record<string, unknown>;
    const subtotalMajor = readNumber(source, 'subtotal');
    const taxMajor = readNumber(source, 'taxAmount', 'tax_amount');
    const totalMajor = readNumber(source, 'total');

    const supplierSource = source.supplier as Record<string, unknown> | undefined;
    const purchaseOrderSource = source.purchaseOrder as Record<string, unknown> | undefined;
    const purchaseOrderFallback = source.purchase_order as Record<string, unknown> | undefined;
    const purchaseOrderCombined = purchaseOrderSource ?? purchaseOrderFallback;
    const document = mapInvoiceDocument((source.document as Record<string, unknown> | undefined) ?? null);
    const attachments = Array.isArray(source.attachments)
        ? (source.attachments as Record<string, unknown>[])
              .map((attachment) => mapInvoiceDocument(attachment))
              .filter((attachment): attachment is DocumentAttachment => Boolean(attachment))
        : undefined;

    return {
        id: readString(source, 'id', 'invoice_id') ?? '',
        companyId: readNumber(source, 'companyId', 'company_id') ?? 0,
        purchaseOrderId: readNumber(source, 'purchaseOrderId', 'purchase_order_id') ?? 0,
        supplierId: readNumber(source, 'supplierId', 'supplier_id') ?? 0,
        invoiceNumber: readString(source, 'invoiceNumber', 'invoice_number') ?? '—',
        invoiceDate: readString(source, 'invoiceDate', 'invoice_date') ?? null,
        currency: readString(source, 'currency') ?? 'USD',
        status: readString(source, 'status') ?? 'draft',
        subtotal: subtotalMajor ?? 0,
        taxAmount: taxMajor ?? 0,
        total: totalMajor ?? 0,
        subtotalMinor: readNumber(source, 'subtotalMinor', 'subtotal_minor') ?? toMinorUnits(subtotalMajor),
        taxAmountMinor: readNumber(source, 'taxAmountMinor', 'tax_amount_minor') ?? toMinorUnits(taxMajor),
        totalMinor: readNumber(source, 'totalMinor', 'total_minor') ?? toMinorUnits(totalMajor),
        supplier: supplierSource
            ? {
                  id: readNumber(supplierSource, 'id') ?? 0,
                  name: readString(supplierSource, 'name') ?? null,
              }
            : null,
        purchaseOrder: purchaseOrderCombined
            ? {
                  id: readNumber(purchaseOrderCombined, 'id') ?? 0,
                  poNumber: readString(purchaseOrderCombined, 'poNumber', 'po_number') ?? null,
              }
            : null,
        document,
        attachments,
        matchSummary: (source.matchSummary as Record<string, number> | undefined) ??
            (source.match_summary as Record<string, number> | undefined),
        createdAt: readString(source, 'createdAt', 'created_at') ?? null,
        updatedAt: readString(source, 'updatedAt', 'updated_at') ?? null,
    };
}

const mapInvoiceLine = (payload: InvoiceLineLike): InvoiceLineDetail => {
    const source = payload as Record<string, unknown>;
    const taxes = Array.isArray(source.taxes) ? (source.taxes as Record<string, unknown>[]) : undefined;

    return {
        id: readNumber(source, 'id') ?? 0,
        poLineId: readNumber(source, 'poLineId', 'po_line_id') ?? 0,
        description: readString(source, 'description') ?? '',
        quantity: readNumber(source, 'quantity') ?? 0,
        uom: readString(source, 'uom') ?? 'ea',
        currency: readString(source, 'currency') ?? undefined,
        unitPrice: readNumber(source, 'unitPrice', 'unit_price') ?? 0,
        unitPriceMinor:
            readNumber(source, 'unitPriceMinor', 'unit_price_minor') ??
            toMinorUnits(readNumber(source, 'unitPrice', 'unit_price')),
        taxCodeIds: taxes?.map((tax) => readNumber(tax, 'taxCodeId', 'tax_code_id') ?? 0).filter((id) => id > 0),
    };
};

export function mapInvoiceDetail(payload: InvoiceLike): InvoiceDetail {
    const summary = mapInvoiceSummary(payload);
    const source = payload as Record<string, unknown>;
    const linesSource = Array.isArray(source.lines) ? (source.lines as InvoiceLineLike[]) : [];
    const matches = Array.isArray(source.matches) ? source.matches : [];

    return {
        ...summary,
        lines: linesSource.map(mapInvoiceLine),
        matches,
    };
}
