import type { DocumentAttachment, GoodsReceiptNoteDetail, GoodsReceiptNoteSummary, GrnLine } from '@/types/sourcing';

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

const toNumber = (value: unknown): number | undefined => {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }
    if (typeof value === 'string') {
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

export function mapGrnSummary(payload: Record<string, unknown>): GoodsReceiptNoteSummary {
    const id = toNumber(pick(payload, 'id', 'grn_id')) ?? 0;
    const grnNumber =
        toStringValue(pick(payload, 'grn_number', 'number', 'reference', 'external_reference')) ??
        (id ? `GRN-${id}` : 'Draft GRN');
    const poId = toNumber(pick(payload, 'po_id', 'purchase_order_id', 'purchaseOrderId')) ?? 0;
    const poNumber = toStringValue(pick(payload, 'po_number', 'purchase_order_number', 'purchaseOrderNumber')) ?? null;
    const supplierId = toNumber(pick(payload, 'supplier_id', 'supplierId')) ?? null;
    const supplierName = toStringValue(pick(payload, 'supplier_name', 'supplierName')) ?? null;
    const status = (pick(payload, 'status') as string | undefined) ?? 'draft';
    const receivedAt = toDateString(pick(payload, 'received_at', 'receivedAt')) ?? null;
    const postedAt = toDateString(pick(payload, 'posted_at', 'postedAt')) ?? null;
    const linesCount = toNumber(pick(payload, 'lines_count', 'linesCount', 'line_count'));
    const attachmentsCount = toNumber(pick(payload, 'attachments_count', 'attachmentsCount'));
    const createdBySource = pick(payload, 'created_by', 'createdBy');
    const createdBy = isRecord(createdBySource)
        ? {
              id: toNumber(pick(createdBySource, 'id')) ?? undefined,
              name: toStringValue(pick(createdBySource, 'name')) ?? undefined,
          }
        : undefined;

    return {
        id,
        grnNumber,
        purchaseOrderId: poId,
        purchaseOrderNumber: poNumber,
        supplierId,
        supplierName,
        status: status as GoodsReceiptNoteSummary['status'],
        receivedAt,
        postedAt,
        linesCount,
        attachmentsCount,
        createdBy,
    };
}

export function mapGrnLine(payload: Record<string, unknown>): GrnLine {
    const poLineId = toNumber(pick(payload, 'po_line_id', 'poLineId', 'line_id')) ?? 0;
    const orderedQty = toNumber(pick(payload, 'ordered_qty', 'orderedQty', 'ordered_quantity', 'quantity')) ?? 0;
    const qtyReceived = toNumber(pick(payload, 'qty_received', 'qtyReceived', 'received_qty')) ?? 0;
    const previouslyReceived = toNumber(pick(payload, 'previously_received', 'received_to_date')) ?? undefined;
    const remainingQty = toNumber(pick(payload, 'remaining_qty', 'remainingQty')) ?? undefined;
    const lineNo = toNumber(pick(payload, 'line_no', 'lineNo')) ?? undefined;
    const description = toStringValue(pick(payload, 'description', 'item_description')) ?? undefined;
    const uom = toStringValue(pick(payload, 'uom', 'unit_of_measure')) ?? undefined;
    const unitPriceMinor = toNumber(pick(payload, 'unit_price_minor', 'unitPriceMinor')) ?? undefined;
    const currency = toStringValue(pick(payload, 'currency')) ?? undefined;
    const variance = toStringValue(pick(payload, 'variance', 'variance_type')) as GrnLine['variance'];

    return {
        id: toNumber(pick(payload, 'id')),
        grnId: toNumber(pick(payload, 'grn_id', 'grnId')),
        poLineId,
        lineNo,
        description,
        orderedQty,
        qtyReceived,
        previouslyReceived,
        remainingQty,
        uom,
        unitPriceMinor,
        currency,
        variance: variance ?? null,
        notes: toStringValue(pick(payload, 'notes', 'note')) ?? undefined,
    };
}

const mapAttachment = (payload: Record<string, unknown>): DocumentAttachment => ({
    id: toNumber(pick(payload, 'id')) ?? 0,
    filename: toStringValue(pick(payload, 'filename', 'name')) ?? 'attachment',
    mime: toStringValue(pick(payload, 'mime', 'content_type', 'mime_type')) ?? 'application/octet-stream',
    sizeBytes: toNumber(pick(payload, 'size_bytes', 'sizeBytes', 'size')) ?? 0,
    createdAt: toDateString(pick(payload, 'created_at', 'createdAt')) ?? undefined,
    downloadUrl: toStringValue(pick(payload, 'download_url', 'downloadUrl')) ?? undefined,
});

export function mapGrnDetail(payload: Record<string, unknown>): GoodsReceiptNoteDetail {
    const summary = mapGrnSummary(payload);
    const reference = toStringValue(pick(payload, 'reference', 'external_reference')) ?? undefined;
    const notes = toStringValue(pick(payload, 'notes', 'note', 'memo')) ?? undefined;
    const linesSource = pick(payload, 'lines');
    const lines = Array.isArray(linesSource)
        ? linesSource.filter(isRecord).map((line) => mapGrnLine(line))
        : [];
    const attachmentsSource = pick(payload, 'attachments');
    const attachments = Array.isArray(attachmentsSource)
        ? attachmentsSource.filter(isRecord).map((attachment) => mapAttachment(attachment))
        : [];
    const timelineSource = pick(payload, 'timeline');
    const timeline = Array.isArray(timelineSource)
        ? timelineSource
              .filter(isRecord)
              .map((entry, index) => ({
                  id: toStringValue(pick(entry, 'id')) ?? `timeline-${index}`,
                  summary: toStringValue(pick(entry, 'summary', 'label', 'title')) ?? 'Event',
                  occurredAt: toDateString(pick(entry, 'occurred_at', 'occurredAt', 'created_at', 'createdAt')) ?? null,
                  actor: isRecord(entry.actor)
                      ? {
                            id: toNumber(pick(entry.actor, 'id')) ?? undefined,
                            name: toStringValue(pick(entry.actor, 'name')) ?? undefined,
                        }
                      : undefined,
              }))
        : undefined;

    return {
        ...summary,
        reference,
        notes,
        lines,
        attachments,
        timeline,
    };
}
