import type { CursorPaginationMeta, OffsetPaginationMeta } from '@/lib/pagination';
import type { ApiKey, RateLimitRule, WebhookDelivery, WebhookSubscription } from '@/sdk';

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

export interface AdminAnalyticsTrendPoint {
    period: string;
    count: number;
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

export interface AdminAnalyticsOverview {
    tenants: AdminAnalyticsTenantsSummary;
    subscriptions: AdminAnalyticsSubscriptionsSummary;
    usage: AdminAnalyticsUsageSummary;
    people: AdminAnalyticsPeopleSummary;
    approvals: AdminAnalyticsApprovalsSummary;
    trends: {
        rfqs: AdminAnalyticsTrendPoint[];
        tenants: AdminAnalyticsTrendPoint[];
    };
    recent: {
        companies: AdminAnalyticsRecentCompany[];
        audit_logs: AuditLogEntry[];
    };
}
