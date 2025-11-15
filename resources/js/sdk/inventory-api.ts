import type { Configuration } from '../../sdk/ts-client/generated';
import { BaseAPI } from '../../sdk/ts-client/generated/runtime';
import type { HTTPHeaders, InitOverrideFunction } from '../../sdk/ts-client/generated/runtime';

import { parseEnvelope, sanitizeQuery } from './api-helpers';

export type MovementType = 'RECEIPT' | 'ISSUE' | 'TRANSFER' | 'ADJUST';

export interface ListInventoryItemsQuery {
    cursor?: string | null;
    perPage?: number;
    sku?: string;
    name?: string;
    category?: string;
    status?: 'active' | 'inactive';
    siteId?: string;
    belowMin?: boolean;
}

export interface InventoryItemMutationPayload {
    sku: string;
    name: string;
    uom: string;
    category?: string | null;
    description?: string | null;
    minStock?: number | null;
    reorderQty?: number | null;
    leadTimeDays?: number | null;
    active?: boolean;
    attributes?: Record<string, string | number | null>;
    defaultLocationId?: string | null;
}

export type UpdateInventoryItemPayload = Partial<InventoryItemMutationPayload>;

export interface ListStockMovementsQuery {
    cursor?: string | null;
    perPage?: number;
    type?: MovementType | MovementType[];
    itemId?: string;
    locationId?: string;
    dateFrom?: string;
    dateTo?: string;
}

export interface MovementLineInput {
    itemId: string | number;
    qty: number;
    uom?: string;
    fromLocationId?: string;
    toLocationId?: string;
    reason?: string;
}

export interface CreateStockMovementPayload {
    type: MovementType;
    movedAt: string;
    lines: MovementLineInput[];
    reference?: {
        source?: 'PO' | 'SO' | 'MANUAL';
        id?: string;
    } | null;
    notes?: string | null;
}

export interface ListLowStockQuery {
    cursor?: string | null;
    perPage?: number;
    locationId?: string;
    siteId?: string;
    category?: string;
}

export interface ListLocationsQuery {
    cursor?: string | null;
    perPage?: number;
    siteId?: string;
    type?: 'site' | 'bin' | 'zone';
    search?: string;
}

export class InventoryModuleApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async listItems(query: ListInventoryItemsQuery = {}, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/inventory/items',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: query.cursor,
                    per_page: query.perPage,
                    sku: query.sku,
                    name: query.name,
                    category: query.category,
                    status: query.status,
                    site_id: query.siteId,
                    below_min: query.belowMin,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async showItem(itemId: string | number, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/inventory/items/${encodeURIComponent(String(itemId))}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async createItem(payload: InventoryItemMutationPayload, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: '/api/inventory/items',
                method: 'POST',
                headers,
                body: {
                    sku: payload.sku,
                    name: payload.name,
                    uom: payload.uom,
                    category: payload.category,
                    description: payload.description,
                    min_stock: payload.minStock,
                    reorder_qty: payload.reorderQty,
                    lead_time_days: payload.leadTimeDays,
                    active: payload.active,
                    attributes: payload.attributes,
                    default_location_id: payload.defaultLocationId,
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async updateItem(
        itemId: string | number,
        payload: UpdateInventoryItemPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ) {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/inventory/items/${encodeURIComponent(String(itemId))}`,
                method: 'PATCH',
                headers,
                body: {
                    sku: payload.sku,
                    name: payload.name,
                    uom: payload.uom,
                    category: payload.category,
                    description: payload.description,
                    min_stock: payload.minStock,
                    reorder_qty: payload.reorderQty,
                    lead_time_days: payload.leadTimeDays,
                    active: payload.active,
                    attributes: payload.attributes,
                    default_location_id: payload.defaultLocationId,
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async listMovements(query: ListStockMovementsQuery = {}, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/inventory/movements',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: query.cursor,
                    per_page: query.perPage,
                    type: query.type,
                    item_id: query.itemId,
                    location_id: query.locationId,
                    date_from: query.dateFrom,
                    date_to: query.dateTo,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async showMovement(movementId: string | number, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/inventory/movements/${encodeURIComponent(String(movementId))}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async createMovement(payload: CreateStockMovementPayload, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: '/api/inventory/movements',
                method: 'POST',
                headers,
                body: {
                    type: payload.type,
                    moved_at: payload.movedAt,
                    reference: payload.reference,
                    notes: payload.notes,
                    lines: payload.lines.map((line) => ({
                        item_id: line.itemId,
                        qty: line.qty,
                        uom: line.uom,
                        from_location_id: line.fromLocationId,
                        to_location_id: line.toLocationId,
                        reason: line.reason,
                    })),
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async listLowStock(query: ListLowStockQuery = {}, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/inventory/low-stock',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: query.cursor,
                    per_page: query.perPage,
                    location_id: query.locationId,
                    site_id: query.siteId,
                    category: query.category,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async listLocations(query: ListLocationsQuery = {}, initOverrides?: RequestInit | InitOverrideFunction) {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/inventory/locations',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: query.cursor,
                    per_page: query.perPage,
                    site_id: query.siteId,
                    type: query.type,
                    search: query.search,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }
}
