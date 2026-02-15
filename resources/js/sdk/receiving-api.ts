import type { Configuration } from '../../sdk/ts-client/generated';
import type {
    HTTPHeaders,
    InitOverrideFunction,
} from '../../sdk/ts-client/generated/runtime';
import { BaseAPI } from '../../sdk/ts-client/generated/runtime';

import { parseEnvelope, sanitizeQuery } from './api-helpers';

export interface ListGrnsQuery {
    cursor?: string | null;
    status?: string;
    supplierId?: number;
    purchaseOrderId?: number;
    receivedFrom?: string;
    receivedTo?: string;
    perPage?: number;
    search?: string;
}

export interface CreateGrnPayload {
    purchaseOrderId: number;
    receivedAt?: string;
    reference?: string;
    notes?: string;
    status?: 'draft' | 'posted';
    lines: Array<{
        poLineId: number;
        quantityReceived: number;
        uom?: string;
        notes?: string;
    }>;
}

export interface AttachGrnFilePayload {
    grnId: number;
    file: File | Blob;
    filename?: string;
}

export interface CreateNcrPayload {
    grnId: number;
    poLineId: number;
    reason: string;
    disposition?: 'rework' | 'return' | 'accept_as_is';
    documents?: number[];
}

export interface UpdateNcrPayload {
    ncrId: number;
    disposition?: 'rework' | 'return' | 'accept_as_is';
}

export class ReceivingApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async listGrns(
        query: ListGrnsQuery = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/receiving/grns',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: query.cursor,
                    status: query.status,
                    supplier_id: query.supplierId,
                    purchase_order_id: query.purchaseOrderId,
                    received_from: query.receivedFrom,
                    received_to: query.receivedTo,
                    per_page: query.perPage,
                    search: query.search,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async showGrn(
        grnId: number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/receiving/grns/${grnId}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async createGrn(
        payload: CreateGrnPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: '/api/receiving/grns',
                method: 'POST',
                headers,
                body: {
                    purchase_order_id: payload.purchaseOrderId,
                    received_at: payload.receivedAt,
                    reference: payload.reference,
                    notes: payload.notes,
                    status: payload.status,
                    lines: payload.lines.map((line) => ({
                        po_line_id: line.poLineId,
                        qty_received: line.quantityReceived,
                        uom: line.uom,
                        notes: line.notes,
                    })),
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async attachGrnFile(
        payload: AttachGrnFilePayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const formData = new FormData();
        formData.append('file', payload.file, payload.filename);

        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/receiving/grns/${payload.grnId}/attachments`,
                method: 'POST',
                headers,
                body: formData,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async createNcr(
        payload: CreateNcrPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/receiving/grns/${payload.grnId}/ncrs`,
                method: 'POST',
                headers,
                body: {
                    purchase_order_line_id: payload.poLineId,
                    reason: payload.reason,
                    disposition: payload.disposition,
                    documents: payload.documents,
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async updateNcr(
        payload: UpdateNcrPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/receiving/ncrs/${payload.ncrId}`,
                method: 'PATCH',
                headers,
                body: {
                    status: 'closed',
                    disposition: payload.disposition,
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }
}
