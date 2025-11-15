import type { MovementType } from '@/sdk';
import type { DocumentAttachment } from './sourcing';

export interface InventoryLocationSummary {
    id: string;
    name: string;
    siteName?: string | null;
    type?: string | null;
    code?: string | null;
}

export interface InventoryLocationOption extends InventoryLocationSummary {
    isDefaultReceiving?: boolean;
    supportsNegative?: boolean;
    onHand?: number | null;
    available?: number | null;
}

export interface StockLocationBalance extends InventoryLocationSummary {
    onHand: number;
    reserved?: number | null;
    available?: number | null;
    supportsNegative?: boolean;
}

export interface InventoryItemSummary {
    id: string;
    sku: string;
    name: string;
    category?: string | null;
    defaultUom: string;
    onHand: number;
    sitesCount: number;
    status: 'active' | 'inactive';
    active: boolean;
    minStock?: number | null;
    reorderQty?: number | null;
    leadTimeDays?: number | null;
    belowMin?: boolean;
    defaultLocationId?: string | null;
    stockByLocation?: StockLocationBalance[];
}

export interface InventoryItemDetail extends InventoryItemSummary {
    description?: string | null;
    attributes?: Record<string, string | number | null>;
    stockByLocation: StockLocationBalance[];
    attachments: DocumentAttachment[];
    reorderRule: {
        minStock: number | null;
        reorderQty: number | null;
        leadTimeDays: number | null;
    };
    recentMovements: StockMovementSummary[];
}

export interface StockMovementLine {
    id?: string;
    itemId: string;
    itemSku?: string | null;
    itemName?: string | null;
    qty: number;
    uom?: string | null;
    fromLocation?: InventoryLocationSummary | null;
    toLocation?: InventoryLocationSummary | null;
    reason?: string | null;
    resultingOnHand?: number | null;
}

export interface StockMovementSummary {
    id: string;
    movementNumber: string;
    type: MovementType;
    movedAt: string;
    status: string;
    lineCount: number;
    fromLocationName?: string | null;
    toLocationName?: string | null;
    referenceLabel?: string | null;
}

export interface StockMovementDetail extends StockMovementSummary {
    lines: StockMovementLine[];
    notes?: string | null;
    referenceMeta?: {
        source?: string | null;
        id?: string | null;
    } | null;
    createdBy?: {
        id?: number | null;
        name?: string | null;
    } | null;
    balances?: Array<{
        locationId: string;
        onHand: number;
        available?: number | null;
    }>;
}

export interface LowStockAlertRow {
    itemId: string;
    sku: string;
    name: string;
    category?: string | null;
    onHand: number;
    minStock: number;
    reorderQty?: number | null;
    leadTimeDays?: number | null;
    uom?: string | null;
    locationName?: string | null;
    siteName?: string | null;
    suggestedReorderDate?: string | null;
}
