import type { Configuration } from '../../sdk/ts-client/generated';
import { BaseAPI } from '../../sdk/ts-client/generated/runtime';
import type { HTTPHeaders, HTTPQuery, InitOverrideFunction } from '../../sdk/ts-client/generated/runtime';

export interface ListMatchCandidatesQuery {
    cursor?: string | null;
    status?: string;
    supplierId?: number;
    dateFrom?: string;
    dateTo?: string;
    perPage?: number;
    search?: string;
}

export interface ResolveMatchPayload {
    invoiceId: number | string;
    purchaseOrderId: number;
    grnIds?: number[];
    decisions: Array<{
        lineId: string;
        type: string;
        status: 'accept' | 'reject' | 'credit' | 'pending';
        notes?: string;
    }>;
}

interface ApiEnvelope<T> {
    status?: 'success' | 'error';
    message?: string | null;
    data?: T;
    meta?: Record<string, unknown> | null;
}

function sanitizeQuery(params: Record<string, unknown>): HTTPQuery {
    return Object.entries(params).reduce<HTTPQuery>((acc, [key, value]) => {
        if (value === undefined || value === null || value === '') {
            return acc;
        }
        acc[key] = value as HTTPQuery[keyof HTTPQuery];
        return acc;
    }, {});
}

async function parseEnvelope<T>(response: Response): Promise<T> {
    const payload = (await response.json()) as ApiEnvelope<T> | T;

    if (payload && typeof payload === 'object' && 'status' in payload) {
        const envelope = payload as ApiEnvelope<T>;

        if (envelope.status === 'success') {
            const data = envelope.data ?? ({} as T);

            if (envelope.meta) {
                if (data && typeof data === 'object' && !Array.isArray(data)) {
                    return {
                        ...(data as Record<string, unknown>),
                        meta: envelope.meta,
                    } as unknown as T;
                }

                return {
                    data,
                    meta: envelope.meta,
                } as unknown as T;
            }

            return data;
        }

        throw new Error(envelope.message ?? 'Request failed');
    }

    return payload as T;
}

export class MatchingApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async listCandidates(
        query: ListMatchCandidatesQuery = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/matching/candidates',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    cursor: query.cursor,
                    status: query.status,
                    supplier_id: query.supplierId,
                    date_from: query.dateFrom,
                    date_to: query.dateTo,
                    per_page: query.perPage,
                    search: query.search,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async resolveMatch(payload: ResolveMatchPayload, initOverrides?: RequestInit | InitOverrideFunction): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: '/api/matching/resolve',
                method: 'POST',
                headers,
                body: {
                    invoice_id: payload.invoiceId,
                    po_id: payload.purchaseOrderId,
                    grn_ids: payload.grnIds,
                    resolutions: payload.decisions.map((decision) => ({
                        line_id: decision.lineId,
                        type: decision.type,
                        status: decision.status,
                        notes: decision.notes,
                    })),
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }
}
