import type { MovementType } from '@/sdk';
import type {
    InventoryItemDetail,
    InventoryItemSummary,
    InventoryLocationOption,
    LowStockAlertRow,
    StockLocationBalance,
    StockMovementDetail,
    StockMovementLine,
    StockMovementSummary,
} from '@/types/inventory';
import type { DocumentAttachment } from '@/types/sourcing';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const toNumber = (value: unknown, fallback = 0): number => {
    const parsed =
        typeof value === 'string' || typeof value === 'number'
            ? Number(value)
            : NaN;
    return Number.isFinite(parsed) ? parsed : fallback;
};

const toString = (value: unknown, fallback = ''): string =>
    typeof value === 'string' ? value : fallback;

const toBoolean = (value: unknown, fallback = false): boolean => {
    if (typeof value === 'boolean') {
        return value;
    }
    if (typeof value === 'number') {
        return value !== 0;
    }
    if (typeof value === 'string') {
        return value === 'true' || value === '1';
    }
    return fallback;
};

function mapLocation(payload: unknown): StockLocationBalance {
    const record = isRecord(payload) ? payload : {};
    return {
        id: toString(record.id ?? record.location_id),
        name: toString(record.name ?? record.location_name ?? 'Unassigned'),
        siteName:
            record.site_name && typeof record.site_name === 'string'
                ? record.site_name
                : null,
        type:
            record.type && typeof record.type === 'string' ? record.type : null,
        code:
            record.code && typeof record.code === 'string' ? record.code : null,
        onHand: toNumber(record.on_hand, 0),
        reserved: Number.isFinite(toNumber(record.reserved, NaN))
            ? toNumber(record.reserved)
            : null,
        available: Number.isFinite(toNumber(record.available, NaN))
            ? toNumber(record.available)
            : null,
        supportsNegative: toBoolean(
            record.supports_negative ?? record.allow_negative,
            false,
        ),
    };
}

function mapAttachment(payload: unknown): DocumentAttachment {
    const record = isRecord(payload) ? payload : {};
    return {
        id: Number(record.id) || 0,
        filename: toString(record.filename ?? record.name ?? 'Attachment'),
        mime: toString(
            record.mime ?? record.mime_type ?? 'application/octet-stream',
        ),
        sizeBytes: Number(record.size_bytes ?? record.size ?? 0) || 0,
        createdAt:
            typeof record.created_at === 'string' ? record.created_at : null,
        downloadUrl:
            typeof record.download_url === 'string'
                ? record.download_url
                : null,
    };
}

function mapMovementLine(payload: unknown): StockMovementLine {
    const record = isRecord(payload) ? payload : {};
    const fromLocation = isRecord(record.from_location)
        ? mapLocation(record.from_location)
        : null;
    const toLocation = isRecord(record.to_location)
        ? mapLocation(record.to_location)
        : null;

    return {
        id: toString(record.id ?? record.line_id ?? ''),
        itemId: toString(record.item_id ?? record.itemId ?? ''),
        itemSku: typeof record.item_sku === 'string' ? record.item_sku : null,
        itemName:
            typeof record.item_name === 'string' ? record.item_name : null,
        qty: toNumber(record.qty ?? record.quantity ?? 0, 0),
        uom: typeof record.uom === 'string' ? record.uom : null,
        fromLocation,
        toLocation,
        reason: typeof record.reason === 'string' ? record.reason : null,
        resultingOnHand: Number.isFinite(
            toNumber(record.resulting_on_hand, NaN),
        )
            ? toNumber(record.resulting_on_hand)
            : null,
    };
}

export function mapInventoryItemSummary(
    payload: Record<string, unknown>,
): InventoryItemSummary {
    const stockByLocationRaw = Array.isArray(payload.stock_by_location)
        ? payload.stock_by_location
        : Array.isArray(payload.locations)
          ? payload.locations
          : [];

    return {
        id: toString(payload.id ?? payload.item_id ?? ''),
        sku: toString(payload.sku ?? ''),
        name: toString(payload.name ?? ''),
        category:
            typeof payload.category === 'string' ? payload.category : null,
        defaultUom: toString(payload.uom ?? payload.default_uom ?? 'EA'),
        onHand: toNumber(payload.on_hand ?? payload.on_hand_qty ?? 0),
        sitesCount: Number.isFinite(toNumber(payload.sites_count, NaN))
            ? toNumber(payload.sites_count)
            : 0,
        status: (typeof payload.status === 'string'
            ? payload.status
            : toBoolean(payload.active, true)
              ? 'active'
              : 'inactive') as 'active' | 'inactive',
        active: toBoolean(payload.active, true),
        minStock: Number.isFinite(toNumber(payload.min_stock, NaN))
            ? toNumber(payload.min_stock)
            : null,
        reorderQty: Number.isFinite(toNumber(payload.reorder_qty, NaN))
            ? toNumber(payload.reorder_qty)
            : null,
        leadTimeDays: Number.isFinite(toNumber(payload.lead_time_days, NaN))
            ? toNumber(payload.lead_time_days)
            : null,
        belowMin: toBoolean(payload.below_min, false),
        defaultLocationId:
            typeof payload.default_location_id === 'string'
                ? payload.default_location_id
                : null,
        stockByLocation: stockByLocationRaw.map((entry) => mapLocation(entry)),
    };
}

export function mapInventoryItemDetail(
    payload: Record<string, unknown>,
): InventoryItemDetail {
    const summary = mapInventoryItemSummary(payload);
    const stockByLocationRaw = Array.isArray(payload.stock_by_location)
        ? payload.stock_by_location
        : Array.isArray(payload.locations)
          ? payload.locations
          : [];
    const attachmentsRaw = Array.isArray(payload.attachments)
        ? payload.attachments
        : [];
    const movementRaw = Array.isArray(payload.recent_movements)
        ? payload.recent_movements
        : [];

    return {
        ...summary,
        description:
            typeof payload.description === 'string'
                ? payload.description
                : null,
        attributes: isRecord(payload.attributes)
            ? (payload.attributes as Record<string, string | number | null>)
            : undefined,
        stockByLocation: stockByLocationRaw.map((entry) => mapLocation(entry)),
        attachments: attachmentsRaw.map((entry) => mapAttachment(entry)),
        reorderRule: {
            minStock: Number.isFinite(toNumber(payload.min_stock, NaN))
                ? toNumber(payload.min_stock)
                : null,
            reorderQty: Number.isFinite(toNumber(payload.reorder_qty, NaN))
                ? toNumber(payload.reorder_qty)
                : null,
            leadTimeDays: Number.isFinite(toNumber(payload.lead_time_days, NaN))
                ? toNumber(payload.lead_time_days)
                : null,
        },
        recentMovements: movementRaw
            .filter((entry): entry is Record<string, unknown> =>
                isRecord(entry),
            )
            .map((entry) => mapStockMovementSummary(entry)),
    };
}

export function mapStockMovementSummary(
    payload: Record<string, unknown>,
): StockMovementSummary {
    return {
        id: toString(payload.id ?? payload.movement_id ?? ''),
        movementNumber: toString(
            payload.number ??
                payload.movement_number ??
                payload.reference ??
                '',
        ),
        type: (typeof payload.type === 'string'
            ? payload.type.toUpperCase()
            : 'RECEIPT') as MovementType,
        movedAt: toString(
            payload.moved_at ?? payload.posted_at ?? new Date().toISOString(),
        ),
        status: toString(payload.status ?? 'posted'),
        lineCount: Number.isFinite(toNumber(payload.lines_count, NaN))
            ? toNumber(payload.lines_count)
            : 0,
        fromLocationName:
            typeof payload.from_location_name === 'string'
                ? payload.from_location_name
                : null,
        toLocationName:
            typeof payload.to_location_name === 'string'
                ? payload.to_location_name
                : null,
        referenceLabel:
            typeof payload.reference_label === 'string'
                ? payload.reference_label
                : null,
    };
}

export function mapStockMovementDetail(
    payload: Record<string, unknown>,
): StockMovementDetail {
    const summary = mapStockMovementSummary(payload);
    const linesRaw = Array.isArray(payload.lines) ? payload.lines : [];

    return {
        ...summary,
        lines: linesRaw.map((line) => mapMovementLine(line)),
        notes: typeof payload.notes === 'string' ? payload.notes : null,
        referenceMeta: isRecord(payload.reference)
            ? {
                  source:
                      typeof payload.reference.source === 'string'
                          ? payload.reference.source
                          : typeof payload.reference.type === 'string'
                            ? payload.reference.type
                            : null,
                  id:
                      typeof payload.reference.id === 'string'
                          ? payload.reference.id
                          : null,
              }
            : null,
        createdBy: isRecord(payload.created_by)
            ? {
                  id: Number(payload.created_by.id) || undefined,
                  name:
                      typeof payload.created_by.name === 'string'
                          ? payload.created_by.name
                          : null,
              }
            : undefined,
        balances: Array.isArray(payload.balances)
            ? payload.balances
                  .filter((entry): entry is Record<string, unknown> =>
                      isRecord(entry),
                  )
                  .map((entry) => ({
                      locationId: toString(entry.location_id ?? entry.id ?? ''),
                      onHand: toNumber(entry.on_hand, 0),
                      available: Number.isFinite(toNumber(entry.available, NaN))
                          ? toNumber(entry.available)
                          : null,
                  }))
            : undefined,
    };
}

export function mapLowStockAlert(
    payload: Record<string, unknown>,
): LowStockAlertRow {
    const minStock = Number.isFinite(toNumber(payload.min_stock, NaN))
        ? toNumber(payload.min_stock)
        : 0;
    return {
        itemId: toString(payload.item_id ?? payload.id ?? ''),
        sku: toString(payload.sku ?? ''),
        name: toString(payload.name ?? ''),
        category:
            typeof payload.category === 'string' ? payload.category : null,
        onHand: toNumber(payload.on_hand ?? payload.quantity ?? 0),
        minStock,
        reorderQty: Number.isFinite(toNumber(payload.reorder_qty, NaN))
            ? toNumber(payload.reorder_qty)
            : null,
        leadTimeDays: Number.isFinite(toNumber(payload.lead_time_days, NaN))
            ? toNumber(payload.lead_time_days)
            : null,
        uom:
            typeof payload.uom === 'string'
                ? payload.uom
                : typeof payload.default_uom === 'string'
                  ? payload.default_uom
                  : null,
        locationName:
            typeof payload.location_name === 'string'
                ? payload.location_name
                : null,
        siteName:
            typeof payload.site_name === 'string' ? payload.site_name : null,
        suggestedReorderDate:
            typeof payload.suggested_reorder_date === 'string'
                ? payload.suggested_reorder_date
                : null,
    };
}

export function mapInventoryLocationOption(
    payload: Record<string, unknown>,
): InventoryLocationOption {
    return {
        id: toString(payload.id ?? payload.location_id ?? ''),
        name: toString(payload.name ?? 'Unnamed location'),
        siteName:
            typeof payload.site_name === 'string' ? payload.site_name : null,
        type: typeof payload.type === 'string' ? payload.type : null,
        code: typeof payload.code === 'string' ? payload.code : null,
        isDefaultReceiving: toBoolean(
            payload.default_receiving ?? payload.is_default_receiving,
            false,
        ),
        supportsNegative: toBoolean(
            payload.supports_negative ?? payload.allow_negative,
            false,
        ),
        onHand: Number.isFinite(toNumber(payload.on_hand, NaN))
            ? toNumber(payload.on_hand)
            : null,
        available: Number.isFinite(toNumber(payload.available, NaN))
            ? toNumber(payload.available)
            : null,
    };
}
