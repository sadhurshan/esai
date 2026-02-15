import type {
    DownloadDocumentType,
    DownloadFormat,
    DownloadJobListMeta,
    DownloadJobStatus,
    DownloadJobSummary,
    DownloadJobUser,
} from '@/types/downloads';

export interface DownloadJobResponseUser {
    id?: number | null;
    name?: string | null;
    email?: string | null;
}

export interface DownloadJobResponseItem {
    id: number;
    document_type: DownloadDocumentType;
    document_id: number;
    reference?: string | null;
    format: DownloadFormat;
    status: DownloadJobStatus;
    filename?: string | null;
    attempts?: number | null;
    meta?: Record<string, unknown> | null;
    error_message?: string | null;
    requested_at?: string | null;
    ready_at?: string | null;
    expires_at?: string | null;
    last_attempted_at?: string | null;
    requested_by?: DownloadJobResponseUser | null;
    download_url?: string | null;
}

export interface DownloadJobResponseMeta {
    per_page?: number;
    next_cursor?: string | null;
    prev_cursor?: string | null;
}

export const mapDownloadJob = (
    payload: DownloadJobResponseItem,
): DownloadJobSummary => ({
    id: payload.id,
    documentType: payload.document_type,
    documentId: payload.document_id,
    reference: payload.reference ?? null,
    format: payload.format,
    status: payload.status,
    filename: payload.filename ?? null,
    attempts: payload.attempts ?? 0,
    meta: payload.meta ?? {},
    errorMessage: payload.error_message ?? null,
    requestedAt: payload.requested_at ?? null,
    readyAt: payload.ready_at ?? null,
    expiresAt: payload.expires_at ?? null,
    lastAttemptedAt: payload.last_attempted_at ?? null,
    requestedBy: normalizeUser(payload.requested_by),
    downloadUrl: payload.download_url ?? null,
});

export const mapDownloadMeta = (
    meta?: DownloadJobResponseMeta | null,
): DownloadJobListMeta => ({
    perPage: meta?.per_page,
    nextCursor: meta?.next_cursor ?? null,
    prevCursor: meta?.prev_cursor ?? null,
});

const normalizeUser = (
    user?: DownloadJobResponseUser | null,
): DownloadJobUser | null => {
    if (!user) {
        return null;
    }

    return {
        id: user.id ?? null,
        name: user.name ?? null,
        email: user.email ?? null,
    } satisfies DownloadJobUser;
};
