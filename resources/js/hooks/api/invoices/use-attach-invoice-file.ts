import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { DocumentAttachment } from '@/types/sourcing';
import { mapInvoiceDocument } from './utils';

const MAX_ATTACHMENT_BYTES = 50 * 1024 * 1024; // 50 MB per documents deep-spec

interface AttachInvoiceFileInput {
    invoiceId: number | string;
    file: File;
}

interface AttachInvoiceFileEnvelope {
    attachment?: Record<string, unknown>;
    document?: Record<string, unknown>;
    data?: Record<string, unknown>;
}

function extractAttachment(
    payload?: AttachInvoiceFileEnvelope | Record<string, unknown>,
): Record<string, unknown> | undefined {
    if (!payload) {
        return undefined;
    }

    if ('attachment' in payload && payload.attachment) {
        return payload.attachment as Record<string, unknown>;
    }

    if ('document' in payload && payload.document) {
        return payload.document as Record<string, unknown>;
    }

    if ('data' in payload && payload.data) {
        return payload.data as Record<string, unknown>;
    }

    return payload as Record<string, unknown>;
}

export function useAttachInvoiceFile(): UseMutationResult<
    DocumentAttachment | null,
    ApiError | Error,
    AttachInvoiceFileInput
> {
    const queryClient = useQueryClient();
    const { notifyPlanLimit } = useAuth();

    return useMutation<
        DocumentAttachment | null,
        ApiError | Error,
        AttachInvoiceFileInput
    >({
        mutationFn: async ({ invoiceId, file }) => {
            const id = String(invoiceId);
            if (!id || id.length === 0) {
                throw new Error(
                    'Invoice id is required to upload attachments.',
                );
            }

            if (!(file instanceof File)) {
                throw new Error('Select a PDF file to upload.');
            }

            const isPdf =
                file.type === 'application/pdf' ||
                file.name.toLowerCase().endsWith('.pdf');
            if (!isPdf) {
                throw new Error(
                    'Only PDF attachments are supported at this time.',
                );
            }

            if (file.size > MAX_ATTACHMENT_BYTES) {
                throw new Error(
                    'Attachment exceeds the 50 MB limit. Compress the PDF and try again.',
                );
            }

            const formData = new FormData();
            formData.append('file', file);

            const response = (await api.post<AttachInvoiceFileEnvelope>(
                `/invoices/${id}/attachments`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                },
            )) as unknown as AttachInvoiceFileEnvelope;

            const attachment = extractAttachment(response);

            return mapInvoiceDocument(attachment ?? null);
        },
        onSuccess: (_, variables) => {
            publishToast({
                variant: 'success',
                title: 'Invoice attachment uploaded',
                description:
                    'PDF uploaded successfully. Refreshing invoice details…',
            });

            void queryClient.invalidateQueries({
                queryKey: queryKeys.invoices.detail(variables.invoiceId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.invoices.list(),
            });
        },
        onError: (error) => {
            if (
                error instanceof ApiError &&
                (error.status === 402 || error.status === 403)
            ) {
                notifyPlanLimit({
                    code: 'invoices',
                    message:
                        error.message ??
                        'Upgrade your plan to upload invoice documents.',
                });
            }

            publishToast({
                variant: 'destructive',
                title: 'Upload failed',
                description:
                    error.message ??
                    'Unable to upload the attachment. Please try again.',
            });
        },
    });
}
