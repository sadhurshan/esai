import { DownloadCloud, RotateCcw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { DownloadJobRow } from '@/components/downloads/download-job-row';
import { EmptyState } from '@/components/empty-state';
import { errorToast, successToast } from '@/components/toasts';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useFormatting } from '@/contexts/formatting-context';
import { useDownloads } from '@/hooks/api/downloads/use-downloads';
import { useRetryDownload } from '@/hooks/api/downloads/use-retry-download';
import type { DownloadJobSummary } from '@/types/downloads';

const PAGE_SIZE = 20;
const POLL_INTERVAL_MS = 15_000;
const TABLE_COLUMNS = 8;

export function DownloadCenterPage() {
    const [cursor, setCursor] = useState<string | null>(null);
    const { formatDate } = useFormatting();

    const downloadsQuery = useDownloads(
        { cursor: cursor ?? undefined, perPage: PAGE_SIZE },
        { refetchInterval: POLL_INTERVAL_MS },
    );
    const retryDownload = useRetryDownload();

    const jobs = downloadsQuery.data?.items ?? [];
    const meta = downloadsQuery.data?.meta;

    const retryingJobId = retryDownload.isPending
        ? retryDownload.variables?.jobId
        : undefined;
    const lastUpdatedLabel = downloadsQuery.dataUpdatedAt
        ? formatDate(downloadsQuery.dataUpdatedAt, {
              dateStyle: 'short',
              timeStyle: 'medium',
          })
        : '—';

    const handleRetry = (jobId: number) => {
        retryDownload.mutate(
            { jobId },
            {
                onSuccess: () =>
                    successToast(
                        'Export queued again',
                        'We will notify you once it is ready to download.',
                    ),
                onError: (error) =>
                    errorToast(
                        'Retry failed',
                        error?.message ??
                            'Please try again or contact support if it persists.',
                    ),
            },
        );
    };

    const handleRefresh = () => {
        downloadsQuery.refetch();
    };

    const handleNextPage = () => {
        if (meta?.nextCursor) {
            setCursor(meta.nextCursor);
        }
    };

    const handlePrevPage = () => {
        setCursor(meta?.prevCursor ?? null);
    };

    const showSkeleton = downloadsQuery.isLoading;

    const skeletonRows = useMemo(() => Array.from({ length: 5 }), []);

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Download Center</title>
            </Helmet>

            <WorkspaceBreadcrumbs />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Documents
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Download Center
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor PDF and CSV exports across RFQs, quotes,
                        purchase orders, invoices, receipts, and credits.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={handleRefresh}
                        disabled={downloadsQuery.isFetching}
                    >
                        <RotateCcw className="h-4 w-4" />
                        Refresh
                    </Button>
                </div>
            </div>

            {downloadsQuery.isError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load download jobs</AlertTitle>
                    <AlertDescription>
                        {downloadsQuery.error?.message ??
                            'We ran into an unexpected issue hitting the API.'}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="overflow-hidden rounded-xl border border-sidebar-border/60 bg-background/60 shadow-sm">
                <div className="relative w-full overflow-x-auto">
                    <table className="w-full min-w-[960px] table-fixed border-separate border-spacing-0 text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr className="text-xs tracking-wide text-muted-foreground uppercase">
                                <th className="px-4 py-3 font-semibold">Job</th>
                                <th className="px-4 py-3 font-semibold">
                                    Document
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Reference
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Format
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Requested
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Expires
                                </th>
                                <th className="px-4 py-3 text-right font-semibold">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {showSkeleton ? (
                                skeletonRows.map((_, index) => (
                                    <tr
                                        key={`download-skeleton-${index}`}
                                        className="border-b border-sidebar-border/40 last:border-b-0"
                                    >
                                        {Array.from({
                                            length: TABLE_COLUMNS,
                                        }).map((__, cellIndex) => (
                                            <td
                                                key={cellIndex}
                                                className="px-4 py-3"
                                            >
                                                <Skeleton className="h-4 w-full" />
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            ) : jobs.length > 0 ? (
                                jobs.map((job: DownloadJobSummary) => (
                                    <DownloadJobRow
                                        key={job.id}
                                        job={job}
                                        onRetry={handleRetry}
                                        isRetrying={retryingJobId === job.id}
                                    />
                                ))
                            ) : (
                                <tr>
                                    <td
                                        colSpan={TABLE_COLUMNS}
                                        className="px-6 py-12"
                                    >
                                        <EmptyState
                                            title="No downloads yet"
                                            description="Trigger an export from a document detail page to generate PDFs or CSVs. Jobs will show here with live status tracking."
                                            icon={
                                                <DownloadCloud className="h-10 w-10" />
                                            }
                                        />
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="flex flex-col gap-3 rounded-xl border border-sidebar-border/60 bg-muted/20 px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <div>
                    Auto-refreshing every {Math.round(POLL_INTERVAL_MS / 1000)}
                    s. Last updated {lastUpdatedLabel}.
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={handlePrevPage}
                        disabled={
                            !meta?.prevCursor || downloadsQuery.isFetching
                        }
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={handleNextPage}
                        disabled={
                            !meta?.nextCursor || downloadsQuery.isFetching
                        }
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
