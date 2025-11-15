import type { InventoryItemSummary, InventoryLocationOption } from '@/types/inventory';

type MovementType = 'RECEIPT' | 'ISSUE' | 'TRANSFER' | 'ADJUST';

export interface MovementFormLineSnapshot {
    itemId?: string;
    qty?: number;
    fromLocationId?: string | null;
}

export interface MovementStockViolation {
    index: number;
    message: string;
}

export interface ValidateMovementStockParams {
    lines: MovementFormLineSnapshot[];
    movementType: MovementType;
    itemsById: Map<string, InventoryItemSummary>;
    locationsById: Map<string, InventoryLocationOption>;
}

const qtyFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 3 });

export function validateMovementStock(params: ValidateMovementStockParams): MovementStockViolation[] {
    const { lines, movementType, itemsById, locationsById } = params;

    if (movementType !== 'ISSUE' && movementType !== 'TRANSFER') {
        return [];
    }

    const runningUsage = new Map<string, number>();
    const violations: MovementStockViolation[] = [];

    lines.forEach((line, index) => {
        if (!line || typeof line.itemId !== 'string') {
            return;
        }

        const qtyValue = typeof line.qty === 'number' ? line.qty : Number(line.qty);
        if (!Number.isFinite(qtyValue) || qtyValue <= 0) {
            return;
        }

        const locationId = typeof line.fromLocationId === 'string' ? line.fromLocationId : undefined;
        if (!locationId) {
            return;
        }

        const locationMeta = locationsById.get(locationId);
        if (locationMeta?.supportsNegative) {
            return;
        }

        const item = itemsById.get(line.itemId);
        const onHand = typeof item?.onHand === 'number' ? item.onHand : null;

        if (onHand === null) {
            return;
        }

        const usedSoFar = runningUsage.get(line.itemId) ?? 0;
        const projected = usedSoFar + qtyValue;

        if (projected > onHand + 1e-6) {
            const remaining = Math.max(onHand - usedSoFar, 0);
            const formattedRemaining = qtyFormatter.format(remaining);
            const unitLabel = item?.defaultUom ? ` ${item.defaultUom}` : '';
            violations.push({
                index,
                message: remaining > 0
                    ? `Only ${formattedRemaining}${unitLabel} available to issue for this SKU.`
                    : `No remaining on-hand stock for this SKU at the selected location.`,
            });
            return;
        }

        runningUsage.set(line.itemId, projected);
    });

    return violations;
}
