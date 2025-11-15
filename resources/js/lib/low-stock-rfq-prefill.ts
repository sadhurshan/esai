import type { LowStockAlertRow } from '@/types/inventory';

export interface LowStockRfqPrefillItem {
    sku?: string;
    name: string;
    quantity: number;
    uom?: string | null;
    requiredDate?: string | null;
}

const PREFILL_KEY = 'inventory.low-stock-rfq-prefill';

function isBrowser(): boolean {
    return typeof window !== 'undefined' && typeof window.sessionStorage !== 'undefined';
}

export function saveLowStockRfqPrefill(items: LowStockRfqPrefillItem[]): void {
    if (!isBrowser() || items.length === 0) {
        return;
    }

    window.sessionStorage.setItem(PREFILL_KEY, JSON.stringify(items));
}

export function consumeLowStockRfqPrefill(): LowStockRfqPrefillItem[] | null {
    if (!isBrowser()) {
        return null;
    }

    const raw = window.sessionStorage.getItem(PREFILL_KEY);

    if (!raw) {
        return null;
    }

    window.sessionStorage.removeItem(PREFILL_KEY);

    try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) {
            return parsed.filter((entry): entry is LowStockRfqPrefillItem => typeof entry === 'object' && entry !== null);
        }
    } catch (error) {
        console.error('Failed to parse low-stock RFQ prefill payload', error);
    }

    return null;
}

export function createPrefillFromAlerts(alerts: LowStockAlertRow[]): LowStockRfqPrefillItem[] {
    return alerts.map((alert, index) => {
        const delta = Math.max(alert.minStock - alert.onHand, 0);
        const fallbackQty = delta > 0 ? delta : 1;
        const quantity = alert.reorderQty && alert.reorderQty > 0 ? alert.reorderQty : fallbackQty;
        const requiredDate = resolveRequiredDate(alert);
        return {
            sku: alert.sku,
            name: alert.name || `Low-stock SKU ${index + 1}`,
            quantity,
            uom: alert.uom ?? 'ea',
            requiredDate,
        } satisfies LowStockRfqPrefillItem;
    });
}

function resolveRequiredDate(alert: LowStockAlertRow): string | null {
    if (alert.suggestedReorderDate) {
        return alert.suggestedReorderDate.slice(0, 10);
    }

    if (typeof alert.leadTimeDays === 'number' && alert.leadTimeDays > 0) {
        const target = new Date();
        target.setDate(target.getDate() + alert.leadTimeDays);
        return target.toISOString().slice(0, 10);
    }

    return null;
}
