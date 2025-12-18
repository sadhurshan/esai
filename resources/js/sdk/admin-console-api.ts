import type { Configuration } from '../../sdk/ts-client/generated';
import { BaseAPI } from '../../sdk/ts-client/generated/runtime';
import type { HTTPHeaders, InitOverrideFunction } from '../../sdk/ts-client/generated/runtime';

import { parseEnvelope, sanitizeQuery } from './api-helpers';
import { toCursorMeta, toOffsetMeta } from '@/lib/pagination';
import type {
    AdminAnalyticsOverview,
    AdminRolesPayload,
    AuditLogFilters,
    AuditLogResponse,
    AuditLogEntry,
    AiEventFilters,
    AiEventResponse,
    AiEventEntry,
    CompanyApprovalFilters,
    CompanyApprovalItem,
    CompanyApprovalResponse,
    CursorPaginatedResponse,
    CreateWebhookPayload,
    ListWebhookDeliveriesParams,
    ListWebhooksParams,
    SupplierApplicationFilters,
    SupplierApplicationItem,
    SupplierApplicationAuditLogResponse,
    SupplierApplicationResponse,
    UpdateRolePayload,
    UpdateWebhookPayload,
    WebhookDeliveryItem,
    WebhookSubscriptionItem,
    WebhookTestPayload,
} from '@/types/admin';

interface PaginatedEnvelope<T> {
    items?: T[];
    meta?: Record<string, unknown> | null;
}

export class AdminConsoleApi extends BaseAPI {
    constructor(configuration: Configuration) {
        super(configuration);
    }

    async analyticsOverview(initOverrides?: RequestInit | InitOverrideFunction): Promise<AdminAnalyticsOverview> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/admin/analytics/overview',
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope<AdminAnalyticsOverview>(response);
    }

    async listRoles(
        companyId?: number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<AdminRolesPayload> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/admin/roles',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    company_id: companyId,
                }),
            },
            initOverrides,
        );

        return parseEnvelope<AdminRolesPayload>(response);
    }

    async updateRole(
        payload: UpdateRolePayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<Record<string, unknown>> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/admin/roles/${payload.roleId}`,
                method: 'PATCH',
                headers,
                body: {
                    permissions: payload.permissions,
                },
            },
            initOverrides,
        );

        return parseEnvelope(response);
    }

    async listAuditLog(
        filters: AuditLogFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<AuditLogResponse> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/admin/audit',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    actor: filters.actor,
                    event: filters.event,
                    resource: filters.resource,
                    from: filters.from,
                    to: filters.to,
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<AuditLogEntry>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies AuditLogResponse;
    }

    async listAiEvents(
        filters: AiEventFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<AiEventResponse> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/admin/ai-events',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    feature: filters.feature,
                    status: filters.status,
                    entity: filters.entity,
                    from: filters.from,
                    to: filters.to,
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<AiEventEntry>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies AiEventResponse;
    }

    async listCompanyApprovals(
        params: CompanyApprovalFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<CompanyApprovalResponse> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: '/api/admin/company-approvals',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    status: params.status,
                    page: params.page,
                    per_page: params.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<CompanyApprovalItem>>(response);

        return {
            items: data.items ?? [],
            meta: toOffsetMeta(data.meta),
        } satisfies CompanyApprovalResponse;
    }

    async listSupplierApplications(
        params: SupplierApplicationFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SupplierApplicationResponse> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: '/api/admin/supplier-applications',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    status: params.status,
                    page: params.page,
                    per_page: params.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<SupplierApplicationItem>>(response);

        return {
            items: data.items ?? [],
            meta: toOffsetMeta(data.meta),
        } satisfies SupplierApplicationResponse;
    }

    async listSupplierApplicationAuditLogs(
        applicationId: number,
        params: { limit?: number } = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SupplierApplicationAuditLogResponse> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: `/api/admin/supplier-applications/${applicationId}/audit-logs`,
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    limit: params.limit,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ items?: AuditLogEntry[] }>(response);

        return {
            items: data.items ?? [],
        } satisfies SupplierApplicationAuditLogResponse;
    }

    async approveCompany(companyId: number, initOverrides?: RequestInit | InitOverrideFunction): Promise<CompanyApprovalItem> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: `/api/admin/company-approvals/${companyId}/approve`,
                method: 'POST',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope<CompanyApprovalItem>(response);
    }

    async rejectCompany(
        companyId: number,
        payload: { reason: string },
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<CompanyApprovalItem> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/admin/company-approvals/${companyId}/reject`,
                method: 'POST',
                headers,
                body: {
                    reason: payload.reason,
                },
            },
            initOverrides,
        );

        return parseEnvelope<CompanyApprovalItem>(response);
    }

    async approveSupplierApplication(
        applicationId: number,
        payload?: { notes?: string | null },
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SupplierApplicationItem> {
        const noteValue = typeof payload?.notes === 'string' ? payload.notes.trim() : '';
        const hasNotes = noteValue.length > 0;
        const headers: HTTPHeaders = hasNotes
            ? {
                  'Content-Type': 'application/json',
              }
            : {};

        const response = await this.request(
            {
                path: `/api/admin/supplier-applications/${applicationId}/approve`,
                method: 'POST',
                headers,
                body: hasNotes ? { notes: noteValue } : undefined,
            },
            initOverrides,
        );

        return parseEnvelope<SupplierApplicationItem>(response);
    }

    async rejectSupplierApplication(
        applicationId: number,
        payload: { notes: string },
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SupplierApplicationItem> {
        const noteValue = payload.notes.trim();
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/admin/supplier-applications/${applicationId}/reject`,
                method: 'POST',
                headers,
                body: { notes: noteValue },
            },
            initOverrides,
        );

        return parseEnvelope<SupplierApplicationItem>(response);
    }

    async sendWebhookTest(
        subscriptionId: string,
        payload: WebhookTestPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<void> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/admin/webhook-subscriptions/${subscriptionId}/test`,
                method: 'POST',
                headers,
                body: payload,
            },
            initOverrides,
        );

        await parseEnvelope(response);
    }

    async listWebhooks(
        params: ListWebhooksParams = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<CursorPaginatedResponse<WebhookSubscriptionItem>> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: '/api/admin/webhook-subscriptions',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    company_id: params.companyId,
                    cursor: params.cursor,
                    per_page: params.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<WebhookSubscriptionItem>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        };
    }

    async createWebhook(
        payload: CreateWebhookPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<WebhookSubscriptionItem> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: '/api/admin/webhook-subscriptions',
                method: 'POST',
                headers,
                body: buildWebhookPayload(payload, { requireCompanyId: true, requireUrl: true, requireEvents: true }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ subscription: WebhookSubscriptionItem }>(response);
        return data.subscription;
    }

    async updateWebhook(
        subscriptionId: string,
        payload: UpdateWebhookPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<WebhookSubscriptionItem> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: `/api/admin/webhook-subscriptions/${subscriptionId}`,
                method: 'PATCH',
                headers,
                body: buildWebhookPayload(payload),
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ subscription: WebhookSubscriptionItem }>(response);
        return data.subscription;
    }

    async deleteWebhook(subscriptionId: string, initOverrides?: RequestInit | InitOverrideFunction): Promise<void> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: `/api/admin/webhook-subscriptions/${subscriptionId}`,
                method: 'DELETE',
                headers,
            },
            initOverrides,
        );

        await parseEnvelope(response);
    }

    async listWebhookDeliveries(
        params: ListWebhookDeliveriesParams = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<CursorPaginatedResponse<WebhookDeliveryItem>> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: '/api/admin/webhook-deliveries',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    company_id: params.companyId,
                    subscription_id: params.subscriptionId,
                    status: params.status,
                    cursor: params.cursor,
                    per_page: params.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<WebhookDeliveryItem>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        };
    }

    async retryWebhookDelivery(deliveryId: string, initOverrides?: RequestInit | InitOverrideFunction): Promise<void> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: `/api/admin/webhook-deliveries/${deliveryId}/retry`,
                method: 'POST',
                headers,
            },
            initOverrides,
        );

        await parseEnvelope(response);
    }
}

interface BuildWebhookPayloadOptions {
    requireCompanyId?: boolean;
    requireUrl?: boolean;
    requireEvents?: boolean;
}

function buildWebhookPayload(
    payload: CreateWebhookPayload | UpdateWebhookPayload,
    options: BuildWebhookPayloadOptions = {},
): Record<string, unknown> {
    const body: Record<string, unknown> = {};

    if (payload.companyId !== undefined) {
        body.company_id = payload.companyId;
    } else if (options.requireCompanyId) {
        throw new Error('companyId is required for webhook creation.');
    }

    if (payload.url !== undefined) {
        body.url = payload.url;
    } else if (options.requireUrl) {
        throw new Error('url is required for webhook creation.');
    }

    if (payload.secret !== undefined) {
        body.secret = payload.secret;
    }

    if (payload.events !== undefined) {
        body.events = payload.events;
    } else if (options.requireEvents) {
        throw new Error('Select at least one event for a webhook.');
    }

    if (payload.active !== undefined) {
        body.active = payload.active;
    }

    if (payload.retryPolicy) {
        const retryPayload: Record<string, unknown> = {};
        if (payload.retryPolicy.max !== undefined) {
            retryPayload.max = payload.retryPolicy.max;
        }

        if (payload.retryPolicy.backoff !== undefined) {
            retryPayload.backoff = payload.retryPolicy.backoff;
        }

        if (payload.retryPolicy.baseSeconds !== undefined) {
            retryPayload.base_sec = payload.retryPolicy.baseSeconds;
        }

        if (Object.keys(retryPayload).length > 0) {
            body.retry_policy_json = retryPayload;
        }
    }

    return body;
}
