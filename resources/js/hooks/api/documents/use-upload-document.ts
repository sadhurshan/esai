import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { api, ApiError } from '@/lib/api';
import type { DocumentAttachment } from '@/types/sourcing';

const MAX_BYTES = 50 * 1024 * 1024;

export interface UploadDocumentInput {
    entity: string;
    entityId: string | number;
    kind: string;
    category: string;
    file: File;
    visibility?: 'private' | 'company' | 'public';
    meta?: Record<string, unknown>;
    watermark?: Record<string, unknown>;
    expiresAt?: string;
    invalidateKey?: readonly unknown[];
}

interface DocumentPayload extends Record<string, unknown> {
    id?: number | string;
    filename?: string;
    mime?: string;
    size_bytes?: number;
    created_at?: string;
    download_url?: string;
}

const mapDocument = (payload?: DocumentPayload): DocumentAttachment | null => {
    if (!payload) {
        return null;
    }

    return {
        id: Number(payload.id) || 0,
        filename: typeof payload.filename === 'string' ? payload.filename : 'Attachment',
        mime: typeof payload.mime === 'string' ? payload.mime : 'application/octet-stream',
        sizeBytes: Number(payload.size_bytes ?? 0) || 0,
        createdAt: typeof payload.created_at === 'string' ? payload.created_at : undefined,
        downloadUrl: typeof payload.download_url === 'string' ? payload.download_url : undefined,
    } satisfies DocumentAttachment;
};

interface UploadResponse extends Record<string, unknown> {
    data?: DocumentPayload;
}

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

const isUploadResponse = (value: unknown): value is UploadResponse => isRecord(value) && 'data' in value;

const isDocumentPayload = (value: unknown): value is DocumentPayload => isRecord(value);

export function useUploadDocument(): UseMutationResult<DocumentAttachment | null, ApiError | Error, UploadDocumentInput> {
    const queryClient = useQueryClient();
    const { notifyPlanLimit } = useAuth();

    return useMutation<DocumentAttachment | null, ApiError | Error, UploadDocumentInput>({
        mutationFn: async ({ entity, entityId, kind, category, file, visibility, meta, watermark, expiresAt }) => {
            const idNumber = Number(entityId);
            if (!Number.isFinite(idNumber) || idNumber <= 0) {
                throw new Error('Unable to resolve document entity id.');
            }

            if (!(file instanceof File)) {
                throw new Error('Select a file to upload.');
            }

            if (file.size > MAX_BYTES) {
                throw new Error('Attachment exceeds the 50 MB limit. Compress the file and try again.');
            }

            const formData = new FormData();
            formData.append('entity', entity);
            formData.append('entity_id', String(idNumber));
            formData.append('kind', kind);
            formData.append('category', category);
            formData.append('file', file);

            if (visibility) {
                formData.append('visibility', visibility);
            }

            if (expiresAt) {
                formData.append('expires_at', expiresAt);
            }

            if (meta) {
                formData.append('meta', JSON.stringify(meta));
            }

            if (watermark) {
                formData.append('watermark', JSON.stringify(watermark));
            }

            const response = await api.post<UploadResponse | DocumentPayload>('/documents', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            const body = response as unknown;
            const payload = isUploadResponse(body) ? body.data : isDocumentPayload(body) ? body : undefined;

            return mapDocument(payload);
        },
        onSuccess: (_, variables) => {
            publishToast({
                variant: 'success',
                title: 'Attachment uploaded',
                description: 'Document added successfully.',
            });

            if (variables.invalidateKey) {
                void queryClient.invalidateQueries({ queryKey: variables.invalidateKey });
            }
        },
        onError: (error) => {
            if (error instanceof ApiError && (error.status === 402 || error.status === 403)) {
                notifyPlanLimit({
                    code: 'documents',
                    message: error.message ?? 'Upgrade your plan to upload documents.',
                });
            }

            publishToast({
                variant: 'destructive',
                title: 'Upload failed',
                description: error.message ?? 'Unable to upload the selected document.',
            });
        },
    });
}
