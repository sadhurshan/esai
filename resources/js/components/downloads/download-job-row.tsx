import { useCallback } from 'react';
import { ArrowDownToLine, Loader2, RotateCcw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useFormatting } from '@/contexts/formatting-context';
import { cn } from '@/lib/utils';
import type { DownloadDocumentType, DownloadJobStatus, DownloadJobSummary } from '@/types/downloads';

const DOCUMENT_LABELS: Record<DownloadDocumentType, string> = {
    rfq: 'RFQ',
    quote: 'Quote',
    purchase_order: 'Purchase Order',
    invoice: 'Invoice',
    grn: 'Goods Receipt',
    credit_note: 'Credit Note',
};

const STATUS_STYLES: Record<DownloadJobStatus, string> = {
    queued: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-100',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-100',
    ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-100',
    failed: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-100',
};

interface DownloadJobRowProps {
    job: DownloadJobSummary;
    onRetry?: (jobId: number) => void;
    isRetrying?: boolean;
}

export function DownloadJobRow({ job, onRetry, isRetrying = false }: DownloadJobRowProps) {
    const { formatDate } = useFormatting();
    const documentLabel = DOCUMENT_LABELS[job.documentType] ?? job.documentType;
    const statusClass = STATUS_STYLES[job.status] ?? STATUS_STYLES.queued;
    const canDownload = job.status === 'ready' && Boolean(job.downloadUrl);
    const canRetry = job.status === 'failed' && Boolean(onRetry);

    const handleDownload = useCallback(() => {
        if (!canDownload || !job.downloadUrl) {
            return;
        }

        if (typeof window !== 'undefined') {
            window.open(job.downloadUrl, '_blank', 'noopener,noreferrer');
        }
    }, [canDownload, job.downloadUrl]);

    return (
        <tr className="border-b border-sidebar-border/40 last:border-b-0">
            <td className="px-4 py-3 align-middle font-mono text-xs text-muted-foreground">#{job.id}</td>
            <td className="px-4 py-3 align-middle">
                <div className="flex flex-col text-sm">
                    <span className="font-semibold text-foreground">{documentLabel}</span>
                    <span className="text-xs text-muted-foreground">ID {job.documentId}</span>
                </div>
            </td>
            <td className="px-4 py-3 align-middle text-sm">
                {job.reference ? (
                    <span className="font-medium text-foreground">{job.reference}</span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>
            <td className="px-4 py-3 align-middle text-sm uppercase tracking-wide text-muted-foreground">
                {job.format?.toUpperCase?.() ?? job.format}
            </td>
            <td className="px-4 py-3 align-middle">
                <Badge variant="secondary" className={cn('text-xs font-semibold uppercase tracking-wide', statusClass)}>
                    {job.status.replace(/_/g, ' ')}
                </Badge>
                <p className="mt-1 text-xs text-muted-foreground">{job.attempts} attempt{job.attempts === 1 ? '' : 's'}</p>
                {job.errorMessage ? <p className="mt-1 text-xs text-destructive">{job.errorMessage}</p> : null}
            </td>
            <td className="px-4 py-3 align-middle text-sm text-muted-foreground">
                {formatDate(job.requestedAt, { dateStyle: 'medium', timeStyle: 'short' })}
                {job.requestedBy?.name ? (
                    <p className="text-xs">{job.requestedBy.name}</p>
                ) : null}
            </td>
            <td className="px-4 py-3 align-middle text-sm text-muted-foreground">
                {formatDate(job.expiresAt, { dateStyle: 'medium', timeStyle: 'short' })}
            </td>
            <td className="px-4 py-3 align-middle">
                <div className="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={handleDownload}
                        disabled={!canDownload || isRetrying}
                    >
                        <ArrowDownToLine className="h-4 w-4" />
                        Download
                    </Button>
                    {canRetry ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => onRetry?.(job.id)}
                            disabled={isRetrying}
                        >
                            {isRetrying ? <Loader2 className="h-4 w-4 animate-spin" /> : <RotateCcw className="h-4 w-4" />}
                            Retry
                        </Button>
                    ) : null}
                </div>
            </td>
        </tr>
    );
}
