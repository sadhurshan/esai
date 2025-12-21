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
    AiModelMetricFilters,
    AiModelMetricResponse,
    AiModelMetricEntry,
    ModelTrainingJob,
    ModelTrainingJobFilters,
    ModelTrainingJobListResponse,
    StartAiTrainingPayload,
    SupplierScrapeJob,
    SupplierScrapeJobFilters,
    SupplierScrapeJobListResponse,
    StartSupplierScrapePayload,
    ScrapedSupplier,
    ScrapedSupplierFilters,
    ScrapedSupplierListResponse,
    ApproveScrapedSupplierPayload,
    DiscardScrapedSupplierPayload,
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

    async listAiModelMetrics(
        filters: AiModelMetricFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<AiModelMetricResponse> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/admin/ai-model-metrics',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    feature: filters.feature,
                    metric_name: filters.metricName,
                    from: filters.from,
                    to: filters.to,
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<AiModelMetricEntry>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies AiModelMetricResponse;
    }

    async listAiTrainingJobs(
        filters: ModelTrainingJobFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<ModelTrainingJobListResponse> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: '/api/admin/ai-training/jobs',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    feature: filters.feature,
                    status: filters.status,
                    company_id: filters.companyId,
                    started_from: filters.startedFrom,
                    started_to: filters.startedTo,
                    created_from: filters.createdFrom,
                    created_to: filters.createdTo,
                    microservice_job_id: filters.microserviceJobId,
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<ModelTrainingJob>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies ModelTrainingJobListResponse;
    }

    async startAiTraining(
        payload: StartAiTrainingPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<ModelTrainingJob> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const body = this.normalizeTrainingPayload(payload);

        const response = await this.request(
            {
                path: '/api/admin/ai-training/start',
                method: 'POST',
                headers,
                body,
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ job: ModelTrainingJob }>(response);

        return data.job;
    }

    async getAiTrainingJob(
        jobId: string | number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<ModelTrainingJob> {
        const headers: HTTPHeaders = {};
        const response = await this.request(
            {
                path: `/api/admin/ai-training/jobs/${jobId}`,
                method: 'GET',
                headers,
            },
            initOverrides,
        );

        return parseEnvelope<ModelTrainingJob>(response);
    }

    async refreshAiTrainingJob(
        jobId: string | number,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<ModelTrainingJob> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: `/api/admin/ai-training/jobs/${jobId}/refresh`,
                method: 'POST',
                headers,
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ job: ModelTrainingJob }>(response);

        return data.job;
    }

    async listSupplierScrapeJobs(
        filters: SupplierScrapeJobFilters,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SupplierScrapeJobListResponse> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: '/api/admin/supplier-scrapes',
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    company_id: filters.companyId,
                    status: filters.status,
                    query: filters.query,
                    region: filters.region,
                    created_from: filters.createdFrom,
                    created_to: filters.createdTo,
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<SupplierScrapeJob>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies SupplierScrapeJobListResponse;
    }

    async startSupplierScrape(
        payload: StartSupplierScrapePayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<SupplierScrapeJob> {
        const headers: HTTPHeaders = {
            'Content-Type': 'application/json',
        };

        const response = await this.request(
            {
                path: '/api/admin/supplier-scrapes/start',
                method: 'POST',
                headers,
                body: {
                    company_id: payload.companyId,
                    query: payload.query,
                    region: payload.region,
                    max_results: payload.maxResults,
                },
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ job: SupplierScrapeJob }>(response);

        return data.job;
    }

    async listScrapedSuppliers(
        jobId: string | number,
        filters: ScrapedSupplierFilters = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<ScrapedSupplierListResponse> {
        const headers: HTTPHeaders = {};

        const response = await this.request(
            {
                path: `/api/admin/supplier-scrapes/${jobId}/results`,
                method: 'GET',
                headers,
                query: sanitizeQuery({
                    search: filters.search,
                    status: filters.status,
                    min_confidence: filters.minConfidence,
                    max_confidence: filters.maxConfidence,
                    cursor: filters.cursor,
                    per_page: filters.perPage,
                }),
            },
            initOverrides,
        );

        const data = await parseEnvelope<PaginatedEnvelope<ScrapedSupplier>>(response);

        return {
            items: data.items ?? [],
            meta: toCursorMeta(data.meta),
        } satisfies ScrapedSupplierListResponse;
    }

    async approveScrapedSupplier(
        scrapedSupplierId: string | number,
        payload: ApproveScrapedSupplierPayload,
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<ScrapedSupplier> {
        const headers: HTTPHeaders = {};
        const body = this.buildApproveScrapedSupplierFormData(payload);

        const response = await this.request(
            {
                path: `/api/admin/scraped-suppliers/${scrapedSupplierId}/approve`,
                method: 'POST',
                headers,
                body,
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ scraped_supplier: ScrapedSupplier }>(response);

        return data.scraped_supplier;
    }

    async discardScrapedSupplier(
        scrapedSupplierId: string | number,
        payload: DiscardScrapedSupplierPayload = {},
        initOverrides?: RequestInit | InitOverrideFunction,
    ): Promise<ScrapedSupplier> {
        const hasNotes = typeof payload.notes === 'string' && payload.notes.trim() !== '';
        const headers: HTTPHeaders = hasNotes
            ? {
                  'Content-Type': 'application/json',
              }
            : {};

        const response = await this.request(
            {
                path: `/api/admin/scraped-suppliers/${scrapedSupplierId}`,
                method: 'DELETE',
                headers,
                body: hasNotes ? { notes: payload.notes } : undefined,
            },
            initOverrides,
        );

        const data = await parseEnvelope<{ scraped_supplier: ScrapedSupplier }>(response);

        return data.scraped_supplier;
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

    private normalizeTrainingPayload(payload: StartAiTrainingPayload): Record<string, unknown> {
        const rawBody = {
            feature: payload.feature,
            company_id: payload.companyId,
            start_date: payload.startDate,
            end_date: payload.endDate,
            horizon: payload.horizon,
            reindex_all: payload.reindexAll,
            dataset_upload_id: payload.datasetUploadId,
            parameters: payload.parameters,
        } satisfies Record<string, unknown>;

        return Object.entries(rawBody).reduce<Record<string, unknown>>((acc, [key, value]) => {
            if (value === undefined || value === null) {
                return acc;
            }

            if (typeof value === 'string' && value.trim() === '') {
                return acc;
            }

            acc[key] = value;
            return acc;
        }, {});
    }

    private buildApproveScrapedSupplierFormData(payload: ApproveScrapedSupplierPayload): FormData {
        const formData = new FormData();
        formData.set('name', payload.name);

        this.appendScalarField(formData, 'website', payload.website);
        this.appendScalarField(formData, 'email', payload.email);
        this.appendScalarField(formData, 'phone', payload.phone);
        this.appendScalarField(formData, 'address', payload.address);
        this.appendScalarField(formData, 'city', payload.city);
        this.appendScalarField(formData, 'state', payload.state);
        this.appendScalarField(formData, 'country', payload.country);
        this.appendScalarField(formData, 'product_summary', payload.productSummary);
        this.appendScalarField(formData, 'notes', payload.notes);
        this.appendScalarField(formData, 'lead_time_days', payload.leadTimeDays);
        this.appendScalarField(formData, 'moq', payload.moq);

        if (payload.attachment) {
            formData.set('attachment', payload.attachment);
        }

        if (payload.attachmentType) {
            formData.set('attachment_type', payload.attachmentType);
        }

        this.appendArrayField(formData, 'certifications[]', payload.certifications);

        const capabilities = payload.capabilities;
        if (capabilities) {
            this.appendArrayField(formData, 'capabilities[methods][]', capabilities.methods);
            this.appendArrayField(formData, 'capabilities[materials][]', capabilities.materials);
            this.appendArrayField(formData, 'capabilities[finishes][]', capabilities.finishes);
            this.appendArrayField(formData, 'capabilities[industries][]', capabilities.industries);
            this.appendArrayField(formData, 'capabilities[tolerances][]', capabilities.tolerances);
            this.appendScalarField(formData, 'capabilities[price_band]', capabilities.priceBand);
            this.appendScalarField(formData, 'capabilities[summary]', capabilities.summary);
        }

        return formData;
    }

    private appendScalarField(formData: FormData, key: string, value: unknown): void {
        if (value === undefined || value === null) {
            return;
        }

        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed === '') {
                return;
            }
            formData.set(key, trimmed);
            return;
        }

        if (typeof value === 'number' && Number.isFinite(value)) {
            formData.set(key, String(value));
            return;
        }

        if (typeof value === 'boolean') {
            formData.set(key, value ? '1' : '0');
        }
    }

    private appendArrayField(
        formData: FormData,
        key: string,
        values?: Array<string | number | null | undefined>,
    ): void {
        if (!Array.isArray(values)) {
            return;
        }

        values.forEach((value) => {
            if (value === undefined || value === null) {
                return;
            }

            const stringValue = typeof value === 'string' ? value.trim() : String(value);
            if (stringValue === '') {
                return;
            }

            formData.append(key, stringValue);
        });
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
