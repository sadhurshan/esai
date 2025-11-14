import { useMemo, useState } from 'react';
import { format } from 'date-fns';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Separator } from '@/components/ui/separator';
import { publishToast } from '@/components/ui/use-toast';
import { useDeleteAttachment, useUploadAttachment } from '@/hooks/api/rfqs';
import type { RfqAttachment } from '@/sdk';
import { Paperclip, Trash2, UploadCloud } from 'lucide-react';

export interface AttachmentUploaderProps {
    rfqId?: string | number;
    attachments: RfqAttachment[];
    isLoading?: boolean;
    canManage?: boolean;
}

function formatSize(sizeBytes?: number | null) {
    if (!sizeBytes || sizeBytes <= 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = sizeBytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(1)} ${units[unitIndex]}`;
}

export function AttachmentUploader({
    rfqId,
    attachments,
    isLoading = false,
    canManage = true,
}: AttachmentUploaderProps) {
    const uploadMutation = useUploadAttachment();
    const deleteMutation = useDeleteAttachment();
    const [pendingDelete, setPendingDelete] = useState<RfqAttachment | null>(null);

    const sortedAttachments = useMemo(() => {
        return [...attachments].sort((left, right) => {
            const leftTime =
                left.uploadedAt instanceof Date ? left.uploadedAt.getTime() : new Date(left.uploadedAt ?? 0).getTime();
            const rightTime =
                right.uploadedAt instanceof Date ? right.uploadedAt.getTime() : new Date(right.uploadedAt ?? 0).getTime();
            return rightTime - leftTime;
        });
    }, [attachments]);

    const handleFileSelection = async (event: React.ChangeEvent<HTMLInputElement>) => {
        if (!rfqId) {
            publishToast({
                variant: 'destructive',
                title: 'Missing RFQ context',
                description: 'Select an RFQ before uploading attachments.',
            });
            return;
        }

        const files = event.target.files;
        if (!files || files.length === 0) {
            return;
        }

        try {
            for (const file of Array.from(files)) {
                await uploadMutation.mutateAsync({
                    rfqId,
                    file,
                });
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to upload attachment.';
            publishToast({
                variant: 'destructive',
                title: 'Upload failed',
                description: message,
            });
        } finally {
            event.target.value = '';
        }
    };

    const handleConfirmDelete = (attachment: RfqAttachment) => {
        setPendingDelete(attachment);
    };

    const handleDeleteAttachment = async () => {
        if (!rfqId || !pendingDelete) {
            setPendingDelete(null);
            return;
        }

        try {
            await deleteMutation.mutateAsync({
                rfqId,
                attachmentId: pendingDelete.id,
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to delete attachment.';
            publishToast({
                variant: 'destructive',
                title: 'Delete failed',
                description: message,
            });
        } finally {
            setPendingDelete(null);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>RFQ documents</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                    <UploadCloud className="h-6 w-6" />
                    <span>Drag & drop or click to upload supporting documents.</span>
                    <input
                        type="file"
                        className="hidden"
                        multiple
                        onChange={handleFileSelection}
                        disabled={!canManage || uploadMutation.isPending}
                    />
                    <span className="text-xs text-muted-foreground">
                        {/* TODO: enforce file type/size constraints once documents module is wired. */}
                        CAD, PDF, and image attachments are supported.
                    </span>
                </label>

                <Separator />

                {isLoading ? (
                    <p className="text-sm text-muted-foreground">Loading attachments…</p>
                ) : sortedAttachments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No attachments uploaded for this RFQ yet.</p>
                ) : (
                    <ul className="space-y-3">
                        {sortedAttachments.map((attachment) => {
                            const uploadedAt =
                                attachment.uploadedAt instanceof Date
                                    ? attachment.uploadedAt
                                    : attachment.uploadedAt
                                    ? new Date(attachment.uploadedAt)
                                    : null;

                            const uploadedLabel = uploadedAt && !Number.isNaN(uploadedAt.getTime()) ? format(uploadedAt, 'PPpp') : 'Pending upload';
                            const uploadedBy =
                                attachment.uploadedBy && typeof attachment.uploadedBy === 'object'
                                    ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                      (attachment.uploadedBy as any)?.name ?? null
                                    : null;

                            return (
                                <li key={attachment.id} className="flex items-center justify-between gap-3 rounded-md border p-3">
                                    <div className="flex items-center gap-3">
                                        <Paperclip className="h-4 w-4 text-muted-foreground" />
                                        <div className="space-y-0.5">
                                            <p className="text-sm font-medium text-foreground">{attachment.filename}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatSize(attachment.sizeBytes)} • {uploadedLabel}
                                                {uploadedBy ? ` • ${uploadedBy}` : ''}
                                            </p>
                                        </div>
                                    </div>
                                    {canManage ? (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleConfirmDelete(attachment)}
                                            disabled={deleteMutation.isPending || uploadMutation.isPending}
                                            aria-label={`Remove ${attachment.filename}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
            <ConfirmDialog
                open={Boolean(pendingDelete)}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                    }
                }}
                title="Delete attachment"
                description="Remove this document from the RFQ? Suppliers will immediately lose access."
                confirmLabel="Delete"
                cancelLabel="Cancel"
                onConfirm={handleDeleteAttachment}
                isProcessing={deleteMutation.isPending}
            />
        </Card>
    );
}
