import { mapInvoiceSummary } from '@/hooks/api/invoices/utils';
import { mapGrnSummary } from '@/hooks/api/receiving/utils';
import type {
    CreditNoteDetail,
    CreditNoteLine,
    CreditNoteSummary,
    DocumentAttachment,
    GoodsReceiptNoteSummary,
    InvoiceSummary,
} from '@/types/sourcing';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const toNumber = (value: unknown): number | undefined => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string') {
        const parsed = Number(value);
        if (!Number.isNaN(parsed)) {
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
        return String(value);
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

const mapDocumentAttachment = (
    payload: Record<string, unknown>,
): DocumentAttachment => ({
    id: toNumber(pick(payload, 'id')) ?? 0,
    filename:
        toStringValue(pick(payload, 'filename', 'name')) ?? 'attachment.pdf',
    mime:
        toStringValue(pick(payload, 'mime', 'content_type')) ??
        'application/pdf',
    sizeBytes: toNumber(pick(payload, 'size_bytes', 'sizeBytes', 'size')) ?? 0,
    createdAt: toDateString(pick(payload, 'created_at', 'createdAt')) ?? null,
    downloadUrl:
        toStringValue(pick(payload, 'download_url', 'downloadUrl')) ?? null,
});

const resolveSupplier = (invoice?: InvoiceSummary | null) => {
    if (!invoice?.supplier) {
        return {
            id: undefined,
            name: undefined,
        };
    }

    return {
        id: invoice.supplier.id ?? undefined,
        name: invoice.supplier.name ?? undefined,
    };
};

export const mapCreditNoteSummary = (
    payload: Record<string, unknown>,
): CreditNoteSummary => {
    const invoiceSource = isRecord(payload.invoice)
        ? (payload.invoice as Record<string, unknown>)
        : isRecord(payload.invoice_id)
          ? ((payload.invoice_id as Record<string, unknown>) ?? undefined)
          : undefined;
    const invoice = invoiceSource
        ? mapInvoiceSummary(invoiceSource)
        : undefined;
    const supplier = resolveSupplier(invoice ?? null);

    return {
        id: toStringValue(pick(payload, 'id')) ?? '',
        creditNumber:
            toStringValue(pick(payload, 'credit_number', 'creditNumber')) ??
            'CN-draft',
        status: (toStringValue(pick(payload, 'status')) ??
            'draft') as CreditNoteSummary['status'],
        supplierName: supplier.name ?? null,
        supplierId: supplier.id ?? null,
        invoiceId:
            toNumber(pick(payload, 'invoice_id', 'invoiceId')) ??
            invoice?.purchaseOrder?.id ??
            undefined,
        invoiceNumber: invoice?.invoiceNumber ?? undefined,
        currency:
            toStringValue(pick(payload, 'currency')) ??
            invoice?.currency ??
            undefined,
        totalMinor:
            toNumber(pick(payload, 'amount_minor', 'amountMinor')) ??
            invoice?.totalMinor ??
            undefined,
        createdAt:
            toDateString(pick(payload, 'created_at', 'createdAt')) ?? undefined,
        issuedAt:
            toDateString(
                pick(payload, 'approved_at', 'issued_at', 'issuedAt'),
            ) ?? undefined,
    };
};

const mapGrnFromPayload = (
    payload: Record<string, unknown>,
): GoodsReceiptNoteSummary | undefined => {
    const source = (payload.goods_receipt_note ?? payload.goodsReceiptNote) as
        | Record<string, unknown>
        | undefined;

    if (!source) {
        return undefined;
    }

    return mapGrnSummary(source);
};

const mapCreditNoteLine = (
    payload: Record<string, unknown>,
    fallbackCurrency?: string,
): CreditNoteLine => {
    const invoiceLineId =
        toNumber(
            pick(
                payload,
                'invoice_line_id',
                'invoiceLineId',
                'id',
                'po_line_id',
                'poLineId',
            ),
        ) ?? 0;
    const qtyInvoiced =
        toNumber(pick(payload, 'qty_invoiced', 'qtyInvoiced', 'quantity')) ?? 0;
    const qtyCredited =
        toNumber(
            pick(
                payload,
                'qty_already_credited',
                'qtyAlreadyCredited',
                'credited_qty',
            ),
        ) ?? 0;
    const qtyToCreditRaw =
        toNumber(pick(payload, 'qty_to_credit', 'qtyToCredit')) ?? undefined;
    const unitPriceMinor =
        toNumber(pick(payload, 'unit_price_minor', 'unitPriceMinor')) ?? 0;
    const totalMinor = toNumber(pick(payload, 'total_minor', 'totalMinor'));
    const description =
        toStringValue(
            pick(
                payload,
                'description',
                'item_description',
                'line_description',
            ),
        ) ?? null;
    const uom = toStringValue(pick(payload, 'uom', 'unit_of_measure')) ?? null;
    const currency =
        toStringValue(pick(payload, 'currency')) ?? fallbackCurrency ?? 'USD';

    const remaining = Math.max((qtyInvoiced ?? 0) - (qtyCredited ?? 0), 0);
    const qtyToCredit =
        qtyToCreditRaw !== undefined ? qtyToCreditRaw : remaining;
    const computedTotalMinor =
        totalMinor !== undefined && totalMinor !== null
            ? totalMinor
            : Math.max(
                  0,
                  Math.round((qtyToCredit ?? 0) * (unitPriceMinor ?? 0)),
              );

    return {
        id: toStringValue(pick(payload, 'id')) ?? undefined,
        invoiceLineId: invoiceLineId || 0,
        description,
        qtyInvoiced,
        qtyAlreadyCredited: qtyCredited,
        qtyToCredit: qtyToCredit ?? 0,
        unitPriceMinor,
        currency,
        totalMinor: computedTotalMinor,
        uom,
    };
};

export const mapCreditNoteDetail = (
    payload: Record<string, unknown>,
): CreditNoteDetail => {
    const summary = mapCreditNoteSummary(payload);
    const invoiceSource =
        (payload.invoice as Record<string, unknown> | undefined) ?? undefined;
    const invoice = invoiceSource
        ? mapInvoiceSummary(invoiceSource)
        : undefined;
    const goodsReceiptNote = mapGrnFromPayload(payload);
    const attachmentsSource = Array.isArray(payload.attachments)
        ? (payload.attachments as Record<string, unknown>[])
        : [];
    const invoiceLinesSource =
        invoiceSource && Array.isArray(invoiceSource['lines'] as unknown[])
            ? (invoiceSource['lines'] as Record<string, unknown>[])
            : [];
    const creditLinesSource = Array.isArray(payload.lines)
        ? (payload.lines as Record<string, unknown>[])
        : [];
    const invoiceLineMap = new Map<number, Record<string, unknown>>();
    invoiceLinesSource.forEach((line) => {
        const idValue = line.id ?? line.invoice_line_id ?? line.invoiceLineId;
        const parsedId =
            typeof idValue === 'number' ? idValue : Number(idValue);
        if (Number.isFinite(parsedId)) {
            invoiceLineMap.set(parsedId, line);
        }
    });

    const creditLineMap = new Map<number, Record<string, unknown>>();
    creditLinesSource.forEach((line) => {
        const idValue = line.invoice_line_id ?? line.invoiceLineId ?? line.id;
        const parsedId =
            typeof idValue === 'number' ? idValue : Number(idValue);
        if (Number.isFinite(parsedId)) {
            creditLineMap.set(parsedId, line);
        }
    });

    const mergedLines: CreditNoteLine[] = invoiceLinesSource.map(
        (invoiceLine) => {
            const idValue =
                invoiceLine.id ??
                invoiceLine.invoice_line_id ??
                invoiceLine.invoiceLineId;
            const parsedId =
                typeof idValue === 'number' ? idValue : Number(idValue);
            const override = Number.isFinite(parsedId)
                ? creditLineMap.get(parsedId)
                : undefined;

            const combined = override
                ? {
                      ...invoiceLine,
                      ...override,
                      invoice_line_id:
                          override.invoice_line_id ??
                          override.invoiceLineId ??
                          parsedId,
                  }
                : invoiceLine;

            return mapCreditNoteLine(
                combined,
                summary.currency ?? invoice?.currency,
            );
        },
    );

    const orphanLines = creditLinesSource.filter((line) => {
        const idValue = line.invoice_line_id ?? line.invoiceLineId ?? line.id;
        const parsedId =
            typeof idValue === 'number' ? idValue : Number(idValue);
        return !Number.isFinite(parsedId) || !invoiceLineMap.has(parsedId);
    });

    const creditLines =
        creditLinesSource.length > 0
            ? [
                  ...mergedLines,
                  ...orphanLines.map((line) =>
                      mapCreditNoteLine(
                          line,
                          summary.currency ?? invoice?.currency,
                      ),
                  ),
              ]
            : mergedLines;

    return {
        ...summary,
        reason: toStringValue(pick(payload, 'reason')) ?? null,
        lines: creditLines,
        attachments: attachmentsSource.map((attachment) =>
            mapDocumentAttachment(attachment),
        ),
        balanceMinor: toNumber(pick(payload, 'balance_minor', 'balanceMinor')),
        notes: toStringValue(pick(payload, 'notes', 'note')) ?? null,
        invoice: invoice ?? null,
        purchaseOrder: null,
        goodsReceiptNote: goodsReceiptNote ?? null,
    };
};
