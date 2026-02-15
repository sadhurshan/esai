import type { Configuration } from '../../sdk/ts-client/generated';
import type {
    HTTPHeaders,
    HTTPQuery,
    InitOverrideFunction,
} from '../../sdk/ts-client/generated/runtime';
import { BaseAPI } from '../../sdk/ts-client/generated/runtime';

export interface ListCreditNotesQuery {
    page?: number;
    perPage?: number;
    status?: string;
    supplierId?: number;
    invoiceId?: number;
    createdFrom?: string;
    createdTo?: string;
    search?: string;
}

export interface CreateCreditNotePayload {
    invoiceId: number;
    reason: string;
    amount?: number | string;
    amountMinor?: number | string;
    purchaseOrderId?: number;
    grnId?: number;
    attachments?: Array<File | Blob | { file: File | Blob; filename?: string }>;
}

export interface AttachCreditNoteFilePayload {
    creditNoteId: number;
    file: File | Blob;
    filename?: string;
}

export interface IssueCreditNotePayload {
    creditNoteId: number | string;
}

export interface ReviewCreditNotePayload {
    creditNoteId: number | string;
    decision: 'approve' | 'reject';
    comment?: string;
}

export interface UpdateCreditNoteLinesPayload {
    creditNoteId: number | string;
    lines: Array<{
        invoiceLineId: number;
        qtyToCredit: number;
        description?: string | null;
        uom?: string | null;
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

function appendAttachment(
    formData: FormData,
    attachment: File | Blob | { file: File | Blob; filename?: string },
    index: number,
): void {
    if ('file' in attachment) {
        formData.append(
            `attachments[${index}]`,
            attachment.file,
            attachment.filename,
        );
        return;
    }

    formData.append(
        `attachments[${index}]`,
        attachment,
        attachment instanceof File ? attachment.name : undefined,
    );
}

export class CreditApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async listCreditNotes(
        query: ListCreditNotesQuery = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/credit-notes',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    page: query.page,
                    per_page: query.perPage,
                    status: query.status,
                    supplier_id: query.supplierId,
                    invoice_id: query.invoiceId,
                    created_from: query.createdFrom,
                    created_to: query.createdTo,
                    search: query.search,
                }),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async getCreditNote(
        creditNoteId: number | string,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/credit-notes/${creditNoteId}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async createCreditNoteFromInvoice(
        payload: CreateCreditNotePayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const formData = new FormData();
        formData.append('reason', payload.reason);

        if (payload.amount !== undefined) {
            formData.append('amount', String(payload.amount));
        }

        if (payload.amountMinor !== undefined) {
            formData.append('amount_minor', String(payload.amountMinor));
        }

        if (payload.purchaseOrderId !== undefined) {
            formData.append(
                'purchase_order_id',
                String(payload.purchaseOrderId),
            );
        }

        if (payload.grnId !== undefined) {
            formData.append('grn_id', String(payload.grnId));
        }

        payload.attachments?.forEach((attachment, index) => {
            appendAttachment(formData, attachment, index);
        });

        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/credit-notes/invoices/${payload.invoiceId}`,
                method: 'POST',
                headers,
                body: formData,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async attachCreditNoteFile(
        payload: AttachCreditNoteFilePayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const formData = new FormData();
        formData.append('file', payload.file, payload.filename);

        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/credit-notes/${payload.creditNoteId}/attachments`,
                method: 'POST',
                headers,
                body: formData,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async issueCreditNote(
        payload: IssueCreditNotePayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/credit-notes/${payload.creditNoteId}/issue`,
                method: 'POST',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async approveCreditNote(
        payload: ReviewCreditNotePayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const body: Record<string, unknown> = {
            decision: payload.decision,
        };

        if (payload.comment) {
            body.comment = payload.comment;
        }

        const response = await this.request(
            {
                path: `/api/credit-notes/${payload.creditNoteId}/approve`,
                method: 'POST',
                headers,
                body: JSON.stringify(body),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async updateCreditNoteLines(
        payload: UpdateCreditNoteLinesPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const body = {
            lines:
                payload.lines?.map((line) => ({
                    invoice_line_id: line.invoiceLineId,
                    qty_to_credit: line.qtyToCredit,
                    description: line.description ?? undefined,
                    uom: line.uom ?? undefined,
                })) ?? [],
        };

        const response = await this.request(
            {
                path: `/api/credit-notes/${payload.creditNoteId}/lines`,
                method: 'PUT',
                headers,
                body: JSON.stringify(body),
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }
}
