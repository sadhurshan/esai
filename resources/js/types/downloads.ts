export type DownloadDocumentType =
    | 'rfq'
    | 'quote'
    | 'purchase_order'
    | 'invoice'
    | 'grn'
    | 'credit_note';

export type DownloadFormat = 'pdf' | 'csv';

export type DownloadJobStatus = 'queued' | 'processing' | 'ready' | 'failed';

export interface DownloadJobUser {
    id?: number | null;
    name?: string | null;
    email?: string | null;
}

export interface DownloadJobSummary {
    id: number;
    documentType: DownloadDocumentType;
    documentId: number | string;
    reference?: string | null;
    format: DownloadFormat;
    status: DownloadJobStatus;
    filename?: string | null;
    attempts: number;
    meta: Record<string, unknown>;
    errorMessage?: string | null;
    requestedAt?: string | null;
    readyAt?: string | null;
    expiresAt?: string | null;
    lastAttemptedAt?: string | null;
    requestedBy?: DownloadJobUser | null;
    downloadUrl?: string | null;
}

export interface DownloadJobListMeta {
    perPage?: number;
    nextCursor?: string | null;
    prevCursor?: string | null;
}

export interface DownloadJobListResult {
    items: DownloadJobSummary[];
    meta?: DownloadJobListMeta | null;
}
