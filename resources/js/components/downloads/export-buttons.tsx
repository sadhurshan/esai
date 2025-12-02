import { useState } from 'react';
import { FileText, Loader2, Table } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { publishToast } from '@/components/ui/use-toast';
import { useRequestExport } from '@/hooks/api/downloads/use-request-export';
import { cn } from '@/lib/utils';
import type { DownloadDocumentType, DownloadFormat } from '@/types/downloads';

interface ExportButtonsProps {
    documentType: DownloadDocumentType;
    documentId: number | string;
    reference?: string | null;
    meta?: Record<string, unknown>;
    size?: 'default' | 'sm';
    className?: string;
    disabled?: boolean;
}

const DOWNLOADS_ROUTE = '/app/downloads';

export function ExportButtons({
    documentType,
    documentId,
    reference,
    meta,
    size = 'sm',
    className,
    disabled = false,
}: ExportButtonsProps) {
    const requestExport = useRequestExport();
    const [pendingFormat, setPendingFormat] = useState<DownloadFormat | null>(null);

    const handleExport = (format: DownloadFormat) => {
        if (documentId === null || documentId === undefined || disabled) {
            return;
        }

        setPendingFormat(format);
        requestExport.mutate(
            {
                documentType,
                documentId,
                reference,
                format,
                meta,
            },
            {
                onSuccess: () => {
                    publishToast({
                        variant: 'success',
                        title: 'Export requested',
                        description: 'Track its progress in the Download Center.',
                        actionLabel: 'Open downloads',
                        actionHref: DOWNLOADS_ROUTE,
                    });
                },
                onError: (error) => {
                    const message = error?.message ?? 'Unable to start the export right now.';
                    publishToast({
                        variant: 'destructive',
                        title: 'Export failed',
                        description: message,
                    });
                },
                onSettled: () => {
                    setPendingFormat(null);
                },
            },
        );
    };

    const isPending = requestExport.isPending;
    const isDisabled = disabled || isPending;

    return (
        <div className={cn('flex flex-wrap items-center gap-2', className)}>
            <Button
                type="button"
                variant="outline"
                size={size}
                onClick={() => handleExport('pdf')}
                disabled={isDisabled}
            >
                {isPending && pendingFormat === 'pdf' ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                    <FileText className="mr-2 h-4 w-4" />
                )}
                Export PDF
            </Button>
            <Button
                type="button"
                variant="ghost"
                size={size}
                onClick={() => handleExport('csv')}
                disabled={isDisabled}
            >
                {isPending && pendingFormat === 'csv' ? (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                ) : (
                    <Table className="mr-2 h-4 w-4" />
                )}
                Export CSV
            </Button>
        </div>
    );
}
