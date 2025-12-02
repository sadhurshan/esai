export type NotificationChannel = 'push' | 'email' | 'both';

export interface NotificationListItem {
    id: number;
    eventType: string;
    title: string;
    body: string;
    entityType?: string | null;
    entityId?: number | string | null;
    channel: NotificationChannel;
    meta: Record<string, unknown>;
    readAt?: string | null;
    createdAt?: string | null;
    updatedAt?: string | null;
}

export interface NotificationListMeta {
    total?: number;
    perPage?: number;
    currentPage?: number;
    lastPage?: number;
    unreadCount?: number;
}

export interface NotificationListResult {
    items: NotificationListItem[];
    meta: NotificationListMeta;
}

export interface NotificationListFilters {
    status?: 'read' | 'unread';
    page?: number;
    per_page?: number;
}

export type EventDeliveryStatus = 'pending' | 'success' | 'failed' | 'dead_letter';

export interface EventDeliveryItem {
    id: number;
    subscriptionId: number;
    endpoint?: string | null;
    event: string;
    status: EventDeliveryStatus;
    attempts: number;
    maxAttempts?: number | null;
    latencyMs?: number | null;
    responseCode?: number | null;
    responseBody?: string | null;
    lastError?: string | null;
    payload?: Record<string, unknown> | null;
    deadLetteredAt?: string | null;
    dispatchedAt?: string | null;
    deliveredAt?: string | null;
    createdAt?: string | null;
}

export interface EventDeliveryFilters {
    cursor?: string | null;
    per_page?: number;
    status?: EventDeliveryStatus;
    event?: string;
    subscription_id?: number;
    endpoint?: string;
    search?: string;
    dlq_only?: boolean;
}

export type NotificationDigestFrequency = 'none' | 'daily' | 'weekly';

export type NotificationEventType =
    | 'rfq_created'
    | 'quote_submitted'
    | 'po_issued'
    | 'grn_posted'
    | 'invoice_created'
    | 'invoice_status_changed'
    | 'rfq.clarification.question'
    | 'rfq.clarification.answer'
    | 'rfq.clarification.amendment'
    | 'rfq.deadline.extended'
    | 'quote.revision.submitted'
    | 'quote.withdrawn'
    | 'rfq_line_awarded'
    | 'rfq_line_lost'
    | 'plan_overlimit'
    | 'certificate_expiry'
    | 'analytics_query'
    | 'approvals.pending'
    | 'rma.raised'
    | 'rma.reviewed'
    | 'rma.closed';

export interface NotificationPreferenceSetting {
    channel: NotificationChannel;
    digest: NotificationDigestFrequency;
}

export type NotificationPreferenceMap = Partial<Record<NotificationEventType, NotificationPreferenceSetting>>;

export interface NotificationPreferenceResponseItem {
    event_type: NotificationEventType;
    channel: NotificationChannel;
    digest: NotificationDigestFrequency;
}
