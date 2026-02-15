import { afterEach, describe, expect, it, vi } from 'vitest';

import type { LowStockAlertRow } from '@/types/inventory';
import { createPrefillFromAlerts } from '../low-stock-rfq-prefill';

describe('createPrefillFromAlerts', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('prefers reorder quantity and suggested reorder date', () => {
        const alerts: LowStockAlertRow[] = [
            {
                itemId: 'item-1',
                sku: 'SKU-1',
                name: 'Precision Bracket',
                onHand: 3,
                minStock: 8,
                reorderQty: 12,
                leadTimeDays: 5,
                uom: 'EA',
                suggestedReorderDate: '2025-01-15T00:00:00Z',
            },
        ];

        const result = createPrefillFromAlerts(alerts);

        expect(result).toEqual([
            expect.objectContaining({
                sku: 'SKU-1',
                name: 'Precision Bracket',
                quantity: 12,
                uom: 'EA',
                requiredDate: '2025-01-15',
            }),
        ]);
    });

    it('falls back to delta and lead time based date', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2025-03-01T00:00:00Z'));

        const alerts: LowStockAlertRow[] = [
            {
                itemId: 'item-2',
                sku: 'SKU-2',
                name: 'Fastener Kit',
                onHand: 4,
                minStock: 10,
                reorderQty: null,
                leadTimeDays: 5,
                uom: null,
            },
        ];

        const result = createPrefillFromAlerts(alerts);

        expect(result).toEqual([
            expect.objectContaining({
                sku: 'SKU-2',
                quantity: 6,
                uom: 'ea',
                requiredDate: '2025-03-06',
            }),
        ]);
    });
});
