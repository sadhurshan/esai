import type { Configuration } from '../../sdk/ts-client/generated';
import type {
    HTTPHeaders,
    InitOverrideFunction,
} from '../../sdk/ts-client/generated/runtime';
import { BaseAPI } from '../../sdk/ts-client/generated/runtime';

import { toCursorMeta } from '@/lib/pagination';
import type {
    AckOrderPayload,
    BuyerOrderFilters,
    CreateShipmentPayload,
    CursorPaginated,
    SalesOrderDetail,
    SalesOrderSummary,
    SupplierOrderFilters,
    UpdateShipmentStatusPayload,
} from '@/types/orders';
import { parseEnvelope, sanitizeQuery } from './api-helpers';

interface PaginatedEnvelope<T> {
    items?: T[];
    data?: T[];
    meta?: Record<string, unknown> | null;
}

export class OrdersAppApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async listSupplierOrders(
        filters: SupplierOrderFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<CursorPaginated<SalesOrderSummary>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/supplier/orders',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                    status: filters.status,
                    buyer_id: filters.buyerCompanyId,
                    date_from: filters.dateFrom,
                    date_to: filters.dateTo,
                    search: filters.search,
                }),
            },
            initOverrides,
        );

        const data =
            await parseEnvelope<PaginatedEnvelope<SalesOrderSummary>>(response);

        return {
            items: data.items ?? data.data ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies CursorPaginated<SalesOrderSummary>;
    }

    async listBuyerOrders(
        filters: BuyerOrderFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<CursorPaginated<SalesOrderSummary>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/buyer/orders',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                    supplier_id: filters.supplierCompanyId,
                    status: filters.status,
                    date_from: filters.dateFrom,
                    date_to: filters.dateTo,
                    search: filters.search,
                }),
            },
            initOverrides,
        );

        const data =
            await parseEnvelope<PaginatedEnvelope<SalesOrderSummary>>(response);

        return {
            items: data.items ?? data.data ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies CursorPaginated<SalesOrderSummary>;
    }

    async showSupplierOrder(
        salesOrderId: string | number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SalesOrderDetail> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/supplier/orders/${salesOrderId}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope<SalesOrderDetail>(response);
    }

    async showBuyerOrder(
        salesOrderId: string | number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SalesOrderDetail> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/buyer/orders/${salesOrderId}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope<SalesOrderDetail>(response);
    }

    async acknowledgeOrder(
        salesOrderId: string | number,
        payload: AckOrderPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SalesOrderDetail> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/supplier/orders/${salesOrderId}/ack`,
                method: 'POST',
                headers,
                body: {
                    decision: payload.decision,
                    reason: payload.reason,
                },
            },
            initOverrides,
        );

        return parseEnvelope<SalesOrderDetail>(response);
    }

    async cancelSupplierOrder(
        salesOrderId: string | number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SalesOrderDetail> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/supplier/orders/${salesOrderId}/cancel`,
                method: 'POST',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope<SalesOrderDetail>(response);
    }

    async createShipment(
        salesOrderId: string | number,
        payload: CreateShipmentPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SalesOrderDetail> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/supplier/orders/${salesOrderId}/shipments`,
                method: 'POST',
                headers,
                body: {
                    carrier: payload.carrier,
                    tracking_number: payload.trackingNumber,
                    shipped_at: payload.shippedAt,
                    notes: payload.notes,
                    lines: payload.lines.map((line) => ({
                        so_line_id: line.soLineId,
                        qty_shipped: line.qtyShipped,
                    })),
                },
            },
            initOverrides,
        );

        return parseEnvelope<SalesOrderDetail>(response);
    }

    async updateShipmentStatus(
        shipmentId: string | number,
        payload: UpdateShipmentStatusPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SalesOrderDetail> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/supplier/shipments/${shipmentId}/status`,
                method: 'POST',
                headers,
                body: {
                    status: payload.status,
                    delivered_at: payload.deliveredAt,
                },
            },
            initOverrides,
        );

        return parseEnvelope<SalesOrderDetail>(response);
    }
}
