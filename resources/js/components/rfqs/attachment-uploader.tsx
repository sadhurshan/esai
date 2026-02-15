import { format } from 'date-fns';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Separator } from '@/components/ui/separator';
import { publishToast } from '@/components/ui/use-toast';
import { useCadExtraction } from '@/hooks/api/documents/use-cad-extraction';
import { useDeleteAttachment, useUploadAttachment } from '@/hooks/api/rfqs';
import type { RfqAttachment } from '@/sdk';
import { Download, Paperclip, Trash2, UploadCloud } from 'lucide-react';

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
    const [pendingDelete, setPendingDelete] = useState<RfqAttachment | null>(
        null,
    );

    const sortedAttachments = useMemo(() => {
        return [...attachments].sort((left, right) => {
            const leftTime =
                left.uploadedAt instanceof Date
                    ? left.uploadedAt.getTime()
                    : new Date(left.uploadedAt ?? 0).getTime();
            const rightTime =
                right.uploadedAt instanceof Date
                    ? right.uploadedAt.getTime()
                    : new Date(right.uploadedAt ?? 0).getTime();
            return rightTime - leftTime;
        });
    }, [attachments]);

    const handleFileSelection = async (
        event: React.ChangeEvent<HTMLInputElement>,
    ) => {
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
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to upload attachment.';
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
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to delete attachment.';
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
                {canManage ? (
                    <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        <UploadCloud className="h-6 w-6" />
                        <span>
                            Drag & drop or click to upload supporting documents.
                        </span>
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
                ) : (
                    <div className="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        Documents shared by the buyer appear below.
                    </div>
                )}

                <Separator />

                {isLoading ? (
                    <p className="text-sm text-muted-foreground">
                        Loading attachments…
                    </p>
                ) : sortedAttachments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No attachments uploaded for this RFQ yet.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {sortedAttachments.map((attachment) => {
                            const uploadedAt =
                                attachment.uploadedAt instanceof Date
                                    ? attachment.uploadedAt
                                    : attachment.uploadedAt
                                      ? new Date(attachment.uploadedAt)
                                      : null;

                            const uploadedLabel =
                                uploadedAt &&
                                !Number.isNaN(uploadedAt.getTime())
                                    ? format(uploadedAt, 'PPpp')
                                    : 'Pending upload';
                            const uploadedBy =
                                attachment.uploadedBy &&
                                typeof attachment.uploadedBy === 'object'
                                    ? // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                      ((attachment.uploadedBy as any)?.name ??
                                      null)
                                    : null;
                            const downloadHref =
                                typeof attachment.url === 'string'
                                    ? attachment.url
                                    : '';
                            const canDownload = downloadHref.length > 0;

                            return (
                                <li
                                    key={attachment.id}
                                    className="rounded-md border p-3"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <Paperclip className="h-4 w-4 text-muted-foreground" />
                                            <div className="space-y-0.5">
                                                <p className="text-sm font-medium text-foreground">
                                                    {attachment.filename}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatSize(
                                                        attachment.sizeBytes,
                                                    )}{' '}
                                                    • {uploadedLabel}
                                                    {uploadedBy
                                                        ? ` • ${uploadedBy}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {canDownload ? (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <a
                                                        href={downloadHref}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        download
                                                        aria-label={`Download ${attachment.filename}`}
                                                        title="Download"
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled
                                                    aria-label="Download unavailable"
                                                >
                                                    <Download className="h-4 w-4" />
                                                </Button>
                                            )}
                                            {canManage ? (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        handleConfirmDelete(
                                                            attachment,
                                                        )
                                                    }
                                                    disabled={
                                                        deleteMutation.isPending ||
                                                        uploadMutation.isPending
                                                    }
                                                    aria-label={`Remove ${attachment.filename}`}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>
                                    <CadInsights attachment={attachment} />
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

function CadInsights({ attachment }: { attachment: RfqAttachment }) {
    const parsedId = attachment.documentId
        ? Number(attachment.documentId)
        : NaN;
    const documentId = Number.isFinite(parsedId) ? parsedId : null;
    const isCad = isCadAttachment(attachment);
    const query = useCadExtraction(documentId ?? undefined);

    if (!isCad || !documentId) {
        return null;
    }

    const extraction = query.data;

    if (query.isLoading || query.isFetching) {
        return (
            <p className="mt-2 text-xs text-muted-foreground">
                Parsing CAD insights…
            </p>
        );
    }

    if (extraction?.status === 'error') {
        return (
            <p className="mt-2 text-xs text-destructive">
                CAD insights unavailable
                {extraction.lastError ? `: ${extraction.lastError}` : '.'}
            </p>
        );
    }

    if (!extraction || extraction.status === 'pending') {
        return (
            <p className="mt-2 text-xs text-muted-foreground">
                CAD insights pending.
            </p>
        );
    }

    const materials = extraction.extracted?.materials ?? [];
    const finishes = extraction.extracted?.finishes ?? [];
    const processes = extraction.extracted?.processes ?? [];
    const tolerances = extraction.extracted?.tolerances ?? [];
    const similarParts = extraction.similarParts ?? [];
    const gdtComplex = Boolean(extraction.gdtFlags?.complex);

    if (
        !materials.length &&
        !finishes.length &&
        !processes.length &&
        !tolerances.length &&
        !similarParts.length &&
        !gdtComplex
    ) {
        return (
            <p className="mt-2 text-xs text-muted-foreground">
                No CAD insights detected.
            </p>
        );
    }

    return (
        <div className="mt-3 rounded-md border border-dashed border-border/60 bg-muted/30 p-3 text-xs">
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-semibold text-foreground">
                    CAD insights
                </span>
                {gdtComplex ? (
                    <Badge variant="destructive" className="text-white">
                        Complex GD&T review
                    </Badge>
                ) : null}
            </div>
            <div className="mt-2 flex flex-wrap gap-2">
                {processes.map((value) => (
                    <Badge key={`process-${value}`} variant="secondary">
                        Process: {value}
                    </Badge>
                ))}
                {materials.map((value) => (
                    <Badge key={`material-${value}`} variant="secondary">
                        Material: {value}
                    </Badge>
                ))}
                {finishes.map((value) => (
                    <Badge key={`finish-${value}`} variant="secondary">
                        Finish: {value}
                    </Badge>
                ))}
                {tolerances.map((value) => (
                    <Badge key={`tolerance-${value}`} variant="secondary">
                        Tolerance: {value}
                    </Badge>
                ))}
            </div>
            {similarParts.length > 0 ? (
                <div className="mt-3">
                    <p className="font-semibold text-foreground">
                        Similar parts
                    </p>
                    <ul className="mt-1 space-y-1 text-muted-foreground">
                        {similarParts.map((part) => (
                            <li key={`part-${part.id}`}>
                                {part.part_number
                                    ? `${part.part_number} · `
                                    : ''}
                                {part.name ?? 'Part'}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}

function isCadAttachment(attachment: RfqAttachment): boolean {
    const name = attachment.filename?.toLowerCase() ?? '';
    const extension = name.split('.').pop() ?? '';
    const cadExtensions = new Set([
        'step',
        'stp',
        'iges',
        'igs',
        'dwg',
        'dxf',
        'sldprt',
        'stl',
        '3mf',
    ]);

    if (cadExtensions.has(extension)) {
        return true;
    }

    const mime = attachment.mime?.toLowerCase() ?? '';
    return mime.includes('cad') || mime.includes('step');
}
