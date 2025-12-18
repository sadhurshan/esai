import { useWatch, type UseFormReturn } from 'react-hook-form';

import { FileDropzone } from '@/components/file-dropzone';
import { Button } from '@/components/ui/button';
import { useUploadDocument } from '@/hooks/api/documents/use-upload-document';
import { useDeleteDocument } from '@/hooks/api/documents/use-delete-document';
import type { SupplierQuoteFormValues } from '@/pages/quotes/supplier-quote-schema';

function formatAttachmentSize(bytes?: number): string {
    if (!bytes || bytes <= 0) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let index = 0;

    while (value >= 1024 && index < units.length - 1) {
        value /= 1024;
        index += 1;
    }

    return `${value.toFixed(1)} ${units[index]}`;
}

export interface QuoteAttachmentsInputProps {
    form: UseFormReturn<SupplierQuoteFormValues>;
    entity: 'rfq' | 'quote';
    entityId?: string | number | null;
    disabled?: boolean;
}

export function QuoteAttachmentsInput({ form, entity, entityId, disabled = false }: QuoteAttachmentsInputProps) {
    const attachments = useWatch({ control: form.control, name: 'attachments' }) ?? [];
    const uploadDocument = useUploadDocument();
    const deleteDocument = useDeleteDocument();
    const numericEntityId = Number(entityId);

    const handleFilesSelected = async (files: File[]) => {
        if (disabled || !Number.isFinite(numericEntityId) || numericEntityId <= 0 || files.length === 0) {
            return;
        }

        for (const file of files) {
            try {
                const document = await uploadDocument.mutateAsync({
                    entity,
                    entityId: numericEntityId,
                    kind: 'quote',
                    category: 'commercial',
                    file,
                });

                if (document) {
                    const current = form.getValues('attachments') ?? [];
                    form.setValue('attachments', [...current, document], { shouldDirty: true, shouldTouch: true });
                }
            } catch {
                break;
            }
        }
    };

    const handleRemove = async (index: number) => {
        if (disabled) {
            return;
        }

        const target = attachments[index];

        if (!target) {
            return;
        }

        try {
            await deleteDocument.mutateAsync({ documentId: target.id });
            const current = form.getValues('attachments') ?? [];
            const next = current.filter((_, idx) => idx !== index);
            form.setValue('attachments', next, { shouldDirty: true, shouldTouch: true });
        } catch {
            // handled by hook toast
        }
    };

    return (
        <div className="space-y-4">
            <FileDropzone
                label="Upload supporting files"
                description="CAD, PDF, DOCX, XLSX up to 50 MB each."
                multiple
                disabled={disabled || uploadDocument.isPending || !Number.isFinite(numericEntityId) || numericEntityId <= 0}
                onFilesSelected={handleFilesSelected}
            />
            {attachments.length === 0 ? (
                <p className="text-sm text-muted-foreground">No attachments uploaded yet.</p>
            ) : (
                <ul className="space-y-2">
                    {attachments.map((attachment, index) => (
                        <li
                            key={`${attachment.id}-${attachment.filename}`}
                            className="flex items-center justify-between rounded-md border border-border/70 px-3 py-2 text-sm"
                        >
                            <div className="space-y-0.5">
                                {attachment.downloadUrl ? (
                                    <a
                                        href={attachment.downloadUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="font-medium text-primary hover:underline"
                                    >
                                        {attachment.filename}
                                    </a>
                                ) : (
                                    <p className="font-medium text-foreground">{attachment.filename}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {attachment.mime ?? 'Document'} · {formatAttachmentSize(attachment.sizeBytes)}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemove(index)}
                                disabled={disabled || deleteDocument.isPending}
                            >
                                Remove
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            {uploadDocument.isPending ? <p className="text-xs text-muted-foreground">Uploading attachments…</p> : null}
        </div>
    );
}
