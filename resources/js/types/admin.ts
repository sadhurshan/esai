import type { CursorPaginationMeta, OffsetPaginationMeta } from '@/lib/pagination';
import type { ApiKey, RateLimitRule, WebhookDelivery, WebhookSubscription } from '@/sdk';
import type { SupplierDocumentType } from '@/types/sourcing';

export type PermissionLevel = 'read' | 'write' | 'admin';

export interface AdminPermissionDefinition {
    key: string;
    label: string;
    description?: string;
    level: PermissionLevel;
    domain: string;
}

export interface AdminPermissionGroup {
    id: string;
    label: string;
    description?: string;
    permissions: AdminPermissionDefinition[];
}

export interface AdminRole {
    id: string;
    slug: string;
    name: string;
    description?: string;
    isSystem?: boolean;
    permissions: string[];
}

export interface AdminRolesPayload {
    roles: AdminRole[];
    permissionGroups: AdminPermissionGroup[];
}

export interface UpdateRolePayload {
    roleId: string;
    permissions: string[];
}

export interface ApiKeyIssueResult {
    token: string;
    apiKey: ApiKey;
}

export interface RateLimitRuleInput {
    id?: number;
    scope: string;
    windowSeconds: number;
    maxRequests: number;
    active?: boolean;
    companyId?: number;
    markForDeletion?: boolean;
}

export interface SyncRateLimitPayload {
    upserts: RateLimitRuleInput[];
    removals: number[];
}

export interface AuditLogActor {
    id?: string | number;
    name?: string | null;
    email?: string | null;
}

export interface AuditLogResourceRef {
    type: string;
    id: string | number;
    label?: string;
}

export interface AuditLogEntry {
    id: string;
    event: string;
    timestamp: string;
    actor?: AuditLogActor | null;
    resource?: AuditLogResourceRef;
    metadata?: Record<string, unknown> | null;
    ipAddress?: string | null;
    userAgent?: string | null;
}

export interface AuditLogFilters extends Record<string, unknown> {
    actor?: string;
    event?: string;
    resource?: string;
    from?: string;
    to?: string;
    cursor?: string | null;
    perPage?: number;
}

export interface AuditLogResponse {
    items: AuditLogEntry[];
    meta?: CursorPaginationMeta;
}

export type AiEventActor = AuditLogActor;
export type AiEventEntityRef = AuditLogResourceRef;

export interface AiEventEntry {
    id: string;
    timestamp?: string | null;
    feature?: string | null;
    status?: string | null;
    latency_ms?: number | null;
    error_message?: string | null;
    user?: AiEventActor | null;
    entity?: AiEventEntityRef | null;
}

export interface AiEventFilters extends Record<string, unknown> {
    feature?: string;
    status?: string;
    entity?: string;
    from?: string;
    to?: string;
    cursor?: string | null;
    perPage?: number;
}

export interface AiEventResponse {
    items: AiEventEntry[];
    meta?: CursorPaginationMeta;
}

export interface AiModelMetricEntityRef {
    type?: string | null;
    id?: string | number | null;
}

export interface AiModelMetricEntry {
    id: string;
    company_id: number;
    feature: string;
    metric_name: string;
    metric_value?: number | null;
    window_start?: string | null;
    window_end?: string | null;
    notes?: Record<string, unknown> | null;
    entity?: AiModelMetricEntityRef | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface AiModelMetricFilters extends Record<string, unknown> {
    feature?: string;
    metricName?: string;
    from?: string;
    to?: string;
    cursor?: string | null;
    perPage?: number;
}

export interface AiModelMetricResponse {
    items: AiModelMetricEntry[];
    meta?: CursorPaginationMeta;
}

export type AiTrainingFeature = 'forecast' | 'risk' | 'rag' | 'actions' | 'workflows' | 'chat';

export type AiTrainingStatus = 'pending' | 'running' | 'completed' | 'failed';

export interface ModelTrainingJob {
    id: string;
    company_id: number;
    feature: AiTrainingFeature | string;
    status: AiTrainingStatus | string;
    microservice_job_id?: string | null;
    parameters?: Record<string, unknown> | null;
    result?: Record<string, unknown> | null;
    error_message?: string | null;
    started_at?: string | null;
    finished_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface ModelTrainingJobFilters extends Record<string, unknown> {
    feature?: AiTrainingFeature | string;
    status?: AiTrainingStatus | string;
    companyId?: number;
    startedFrom?: string;
    startedTo?: string;
    createdFrom?: string;
    createdTo?: string;
    microserviceJobId?: string;
    cursor?: string | null;
    perPage?: number;
}

export interface ModelTrainingJobListResponse {
    items: ModelTrainingJob[];
    meta?: CursorPaginationMeta;
}

export interface StartAiTrainingPayload {
    feature: AiTrainingFeature | string;
    companyId: number;
    startDate?: string;
    endDate?: string;
    horizon?: number;
    reindexAll?: boolean;
    datasetUploadId?: string;
    parameters?: Record<string, unknown>;
}

export type SupplierScrapeJobStatus = 'pending' | 'running' | 'completed' | 'failed';

export interface SupplierScrapeJob {
    id: string;
    company_id: number | null;
    user_id?: number | null;
    query: string;
    region?: string | null;
    status?: SupplierScrapeJobStatus | string;
    result_count?: number | null;
    error_message?: string | null;
    parameters?: Record<string, unknown> | null;
    started_at?: string | null;
    finished_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface SupplierScrapeJobFilters extends Record<string, unknown> {
    companyId?: number;
    status?: SupplierScrapeJobStatus | string;
    query?: string;
    region?: string;
    createdFrom?: string;
    createdTo?: string;
    cursor?: string | null;
    perPage?: number;
}

export interface SupplierScrapeJobListResponse {
    items: SupplierScrapeJob[];
    meta?: CursorPaginationMeta;
}

export interface StartSupplierScrapePayload {
    companyId?: number;
    query: string;
    region?: string | null;
    maxResults: number;
}

export type ScrapedSupplierStatus = 'pending' | 'approved' | 'discarded';

export interface ScrapedSupplier {
    id: string;
    scrape_job_id: number;
    company_id: number | null;
    name: string;
    website?: string | null;
    description?: string | null;
    industry_tags?: string[];
    address?: string | null;
    city?: string | null;
    state?: string | null;
    country?: string | null;
    phone?: string | null;
    email?: string | null;
    contact_person?: string | null;
    certifications?: string[];
    product_summary?: string | null;
    source_url?: string | null;
    confidence?: number | null;
    metadata?: Record<string, unknown> | null;
    status?: ScrapedSupplierStatus | string | null;
    approved_supplier_id?: number | null;
    reviewed_by?: number | null;
    reviewed_at?: string | null;
    review_notes?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface ScrapedSupplierFilters extends Record<string, unknown> {
    search?: string;
    status?: ScrapedSupplierStatus | string;
    minConfidence?: number;
    maxConfidence?: number;
    cursor?: string | null;
    perPage?: number;
}

export interface ScrapedSupplierListResponse {
    items: ScrapedSupplier[];
    meta?: CursorPaginationMeta;
}

export interface ApproveScrapedSupplierPayload {
    name: string;
    website?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    country?: string | null;
    capabilities?: {
        methods?: string[];
        materials?: string[];
        finishes?: string[];
        industries?: string[];
        tolerances?: string[];
        priceBand?: string | null;
        summary?: string | null;
    };
    productSummary?: string | null;
    certifications?: string[];
    notes?: string | null;
    leadTimeDays?: number | null;
    moq?: number | null;
    attachment?: File | null;
    attachmentType?: SupplierDocumentType;
}

export interface DiscardScrapedSupplierPayload {
    notes?: string | null;
}

export interface SupplierApplicationAuditLogResponse {
    items: AuditLogEntry[];
}

export interface WebhookTestResult {
    status: 'ok' | 'failed';
    latencyMs?: number;
    responseCode?: number;
    error?: string;
}

export interface WebhookTestPayload {
    event: string;
    payload?: Record<string, unknown>;
}

export interface WebhookRetryPolicyInput {
    max?: number;
    backoff?: 'exponential' | 'linear' | string;
    baseSeconds?: number;
}

export interface CreateWebhookPayload {
    companyId: number;
    url: string;
    events: string[];
    secret?: string;
    active?: boolean;
    retryPolicy?: WebhookRetryPolicyInput;
}

export interface UpdateWebhookPayload {
    companyId?: number;
    url?: string;
    secret?: string;
    events?: string[];
    active?: boolean;
    retryPolicy?: WebhookRetryPolicyInput;
}

export interface ListWebhooksParams {
    companyId?: number;
    cursor?: string | null;
    perPage?: number;
}

export interface ListWebhookDeliveriesParams extends Record<string, unknown> {
    companyId?: number;
    subscriptionId?: string;
    status?: string;
    cursor?: string | null;
    perPage?: number;
}

export interface CursorPaginatedResponse<T> {
    items: T[];
    meta?: CursorPaginationMeta;
}

export type ApiKeyListItem = ApiKey;
export type WebhookSubscriptionItem = WebhookSubscription;
export type WebhookDeliveryItem = WebhookDelivery;
export type RateLimitRuleItem = RateLimitRule;

export type CompanyStatusValue =
    | 'pending'
    | 'pending_verification'
    | 'active'
    | 'suspended'
    | 'trial'
    | 'closed'
    | 'rejected';

export interface CompanyApprovalItem {
    id: number;
    name: string;
    slug: string;
    status: CompanyStatusValue | string;
    supplier_status?: string | null;
    directory_visibility?: string | null;
    supplier_profile_completed_at?: string | null;
    is_verified?: boolean;
    verified_at?: string | null;
    verified_by?: number | null;
    registration_no?: string | null;
    tax_id?: string | null;
    country?: string | null;
    email_domain?: string | null;
    primary_contact_name?: string | null;
    primary_contact_email?: string | null;
    primary_contact_phone?: string | null;
    address?: string | null;
    phone?: string | null;
    website?: string | null;
    region?: string | null;
    rejection_reason?: string | null;
    owner_user_id?: number | null;
    created_at?: string | null;
    updated_at?: string | null;
    has_completed_onboarding?: boolean;
}

export interface CompanyApprovalFilters extends Record<string, unknown> {
    status?: CompanyStatusValue | string;
    page?: number;
    perPage?: number;
}

export interface CompanyApprovalResponse {
    items: CompanyApprovalItem[];
    meta?: OffsetPaginationMeta;
}

export interface CompaniesHouseAddress {
    care_of?: string | null;
    po_box?: string | null;
    address_line_1?: string | null;
    address_line_2?: string | null;
    locality?: string | null;
    region?: string | null;
    postal_code?: string | null;
    country?: string | null;
}

export interface CompaniesHouseAccounts {
    accounting_reference_date?: Record<string, string | undefined> | null;
    next_due?: string | null;
    last_accounts?: Record<string, string | undefined> | null;
    overdue?: boolean | null;
}

export interface CompaniesHouseConfirmationStatement {
    next_due?: string | null;
    next_made_up_to?: string | null;
    overdue?: boolean | null;
}

export interface CompaniesHousePreviousName {
    name?: string | null;
    ceased_on?: string | null;
    effective_from?: string | null;
}

export interface CompaniesHouseProfile {
    company_name?: string | null;
    company_number?: string | null;
    company_status?: string | null;
    type?: string | null;
    jurisdiction?: string | null;
    sic_codes?: string[];
    date_of_creation?: string | null;
    undeliverable_registered_office_address?: boolean | null;
    has_been_liquidated?: boolean | null;
    can_file?: boolean | null;
    registered_office_address?: CompaniesHouseAddress | null;
    accounts?: CompaniesHouseAccounts | null;
    confirmation_statement?: CompaniesHouseConfirmationStatement | null;
    previous_company_names?: CompaniesHousePreviousName[];
    retrieved_at?: string | null;
    raw?: Record<string, unknown> | null;
}

export interface CompaniesHouseLookupResponse {
    profile?: CompaniesHouseProfile | null;
}

export type SupplierApplicationStatusValue = 'pending' | 'approved' | 'rejected';

export interface SupplierApplicationFilters extends Record<string, unknown> {
    status?: SupplierApplicationStatusValue | 'all' | string;
    page?: number;
    perPage?: number;
}

export interface SupplierApplicationDocumentSummary {
    id: number;
    supplier_id?: number | null;
    company_id: number;
    document_id?: number | null;
    type: string;
    status?: string | null;
    path?: string | null;
    download_url?: string | null;
    filename?: string | null;
    mime: string;
    size_bytes: number;
    issued_at?: string | null;
    expires_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface SupplierApplicationFormPayload {
    description?: string;
    capabilities?: Record<string, string[] | undefined>;
    address?: string;
    city?: string;
    country?: string;
    moq?: number;
    min_order_qty?: number;
    lead_time_days?: number;
    certifications?: string[];
    facilities?: string;
    website?: string;
    contact?: {
        name?: string;
        email?: string;
        phone?: string;
    };
    notes?: string;
    documents?: number[];
    [key: string]: unknown;
}

export interface SupplierApplicationItem {
    id: number;
    company_id: number;
    submitted_by?: number | null;
    status: SupplierApplicationStatusValue | string;
    form_json?: SupplierApplicationFormPayload | null;
    reviewed_by?: number | null;
    reviewed_at?: string | null;
    notes?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    company?: CompanyApprovalItem | null;
    documents?: SupplierApplicationDocumentSummary[];
}

export interface SupplierApplicationResponse {
    items: SupplierApplicationItem[];
    meta?: OffsetPaginationMeta;
}

export interface AdminAnalyticsTrendPoint {
    period: string;
    count: number;
}

export interface AdminWorkflowAlertCompany {
    id: number;
    name?: string | null;
}

export interface AdminWorkflowAlertOwner {
    id: number;
    name?: string | null;
    email?: string | null;
}

export interface AdminWorkflowAlert {
    workflow_id: string;
    workflow_type?: string | null;
    status: string;
    company?: AdminWorkflowAlertCompany | null;
    owner?: AdminWorkflowAlertOwner | null;
    current_step?: number | null;
    current_step_label?: string | null;
    last_event_type?: string | null;
    last_event_time?: string | null;
    updated_at?: string | null;
}

export interface AdminWorkflowMetrics {
    window_days: number;
    total_started: number;
    completed: number;
    in_progress: number;
    failed: number;
    completion_rate: number;
    avg_step_approval_minutes: number | null;
    failed_alerts: AdminWorkflowAlert[];
}

export interface AdminAnalyticsRecentCompanyPlan {
    id: number;
    name: string;
    code?: string | null;
}

export interface AdminAnalyticsRecentCompany {
    id: number;
    name: string;
    status?: CompanyStatusValue | string | null;
    created_at?: string | null;
    trial_ends_at?: string | null;
    plan?: AdminAnalyticsRecentCompanyPlan | null;
    rfqs_monthly_used?: number | null;
    storage_used_mb?: number | null;
}

export interface AdminAnalyticsTenantsSummary {
    total: number;
    active: number;
    trialing: number;
    suspended: number;
    pending: number;
}

export interface AdminAnalyticsSubscriptionsSummary {
    active: number;
    trialing: number;
    past_due: number;
}

export interface AdminAnalyticsUsageSummary {
    rfqs_month_to_date: number;
    rfqs_last_month: number;
    rfqs_capacity_used: number;
    quotes_month_to_date: number;
    purchase_orders_month_to_date: number;
    storage_used_mb: number;
    avg_storage_used_mb: number;
}

export interface AdminAnalyticsPeopleSummary {
    users_total: number;
    active_last_7_days: number;
    listed_suppliers: number;
}

export interface AdminAnalyticsApprovalsSummary {
    pending_companies: number;
    pending_supplier_applications: number;
}

export interface AdminAnalyticsCopilotMetrics {
    window_days: number;
    forecast_requests: number;
    help_requests: number;
}

export interface AiAdminUsageActionMetrics {
    planned: number;
    approved: number;
    approval_rate: number | null;
}

export interface AiAdminUsageForecastMetrics {
    generated: number;
    errors: number;
}

export interface AiAdminUsageHelpMetrics {
    total: number;
}

export interface AiAdminUsageToolErrorBreakdown {
    feature: string;
    count: number;
}

export interface AiAdminUsageToolErrorMetrics {
    total: number;
    by_feature: AiAdminUsageToolErrorBreakdown[];
}

export interface AiAdminUsageMetrics {
    window_days: number;
    window_start: string;
    window_end: string;
    actions: AiAdminUsageActionMetrics;
    forecasts: AiAdminUsageForecastMetrics;
    help_requests: AiAdminUsageHelpMetrics;
    tool_errors: AiAdminUsageToolErrorMetrics;
}

export interface AiAdminUsageMetricsResponse {
    metrics: AiAdminUsageMetrics;
}

export interface AdminAnalyticsOverview {
    tenants: AdminAnalyticsTenantsSummary;
    subscriptions: AdminAnalyticsSubscriptionsSummary;
    usage: AdminAnalyticsUsageSummary;
    people: AdminAnalyticsPeopleSummary;
    approvals: AdminAnalyticsApprovalsSummary;
    workflows: AdminWorkflowMetrics;
    copilot: AdminAnalyticsCopilotMetrics;
    trends: {
        rfqs: AdminAnalyticsTrendPoint[];
        tenants: AdminAnalyticsTrendPoint[];
    };
    recent: {
        companies: AdminAnalyticsRecentCompany[];
        audit_logs: AuditLogEntry[];
    };
}
