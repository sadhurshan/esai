import { Fragment, useState } from 'react';
import { ChevronLeft, ChevronRight, FileText, Info } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { CursorPaginationMeta } from '@/lib/pagination';
import type { AuditLogEntry } from '@/types/admin';
import { useFormatting } from '@/contexts/formatting-context';

export interface AuditLogTableProps {
    entries: AuditLogEntry[];
    meta?: CursorPaginationMeta;
    isLoading?: boolean;
    onNextPage?: (cursor: string | null) => void;
    onPrevPage?: (cursor: string | null) => void;
}

export function AuditLogTable({ entries, meta, isLoading = false, onNextPage, onPrevPage }: AuditLogTableProps) {
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const { formatDate } = useFormatting();

    if (isLoading) {
        return <AuditLogSkeleton />;
    }

    if (!entries.length) {
        return (
            <EmptyState
                icon={<FileText className="h-10 w-10" aria-hidden />}
                title="No audit events"
                description="Adjust filters or perform an administrative action to populate audit history."
            />
        );
    }

    const nextCursor = meta?.nextCursor ?? null;
    const prevCursor = meta?.prevCursor ?? null;

    return (
        <div className="overflow-hidden rounded-xl border">
            <table className="min-w-full divide-y divide-muted text-sm">
                <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th className="px-4 py-3 font-semibold">Timestamp</th>
                        <th className="px-4 py-3 font-semibold">Actor</th>
                        <th className="px-4 py-3 font-semibold">Event</th>
                        <th className="px-4 py-3 font-semibold">Resource</th>
                        <th className="px-4 py-3 font-semibold">IP / Agent</th>
                        <th className="px-4 py-3 font-semibold text-right">Metadata</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-muted bg-background">
                    {entries.map((entry) => {
                        const isExpanded = expandedId === entry.id;
                        const resourceLabel = entry.resource
                            ? `${entry.resource.type}#${entry.resource.id}`
                            : '—';
                        const actorName = entry.actor?.name ?? 'System event';
                        const actorDetail = entry.actor?.email
                            ? entry.actor.email
                            : entry.actor?.id
                              ? `User #${entry.actor.id}`
                              : 'Automated action';
                        const timestampShort = formatDate(entry.timestamp, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        });
                        const timestampLong = formatDate(entry.timestamp, {
                            dateStyle: 'full',
                            timeStyle: 'long',
                        });
                        return (
                            <Fragment key={entry.id}>
                                <tr className="align-top hover:bg-muted/30">
                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-foreground">{timestampShort}</span>
                                            <span>{timestampLong}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span className="font-medium text-foreground">{actorName}</span>
                                            <span className="text-xs text-muted-foreground">{actorDetail}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline" className="font-mono text-[11px] uppercase">
                                            {entry.event}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col text-xs text-muted-foreground">
                                            <span className="font-medium text-foreground">{resourceLabel}</span>
                                            {entry.resource?.label ? <span>{entry.resource.label}</span> : null}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                        <div className="flex flex-col">
                                            <span>{entry.ipAddress ?? '—'}</span>
                                            <span className="truncate">{entry.userAgent ?? '—'}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setExpandedId(isExpanded ? null : entry.id)}
                                        >
                                            {isExpanded ? 'Hide JSON' : 'View JSON'}
                                        </Button>
                                    </td>
                                </tr>
                                {isExpanded ? (
                                    <tr className="bg-muted/20">
                                        <td colSpan={6} className="px-4 py-3">
                                            {entry.metadata ? (
                                                <pre className="max-h-64 overflow-x-auto rounded-lg bg-background/80 p-3 text-xs text-foreground">
                                                    {JSON.stringify(entry.metadata, null, 2)}
                                                </pre>
                                            ) : (
                                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <Info className="h-4 w-4" aria-hidden /> No metadata for this event.
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ) : null}
                            </Fragment>
                        );
                    })}
                </tbody>
            </table>
            <div className="flex flex-col gap-3 border-t bg-muted/20 p-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>{entries.length} result{entries.length === 1 ? '' : 's'}</span>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" size="sm" disabled={!prevCursor} onClick={() => onPrevPage?.(prevCursor)}>
                        <ChevronLeft className="mr-1 h-4 w-4" aria-hidden /> Previous
                    </Button>
                    <Button type="button" variant="outline" size="sm" disabled={!nextCursor} onClick={() => onNextPage?.(nextCursor)}>
                        Next <ChevronRight className="ml-1 h-4 w-4" aria-hidden />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function AuditLogSkeleton() {
    return (
        <div className="space-y-2 rounded-xl border p-4">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="grid gap-3 rounded-lg border bg-muted/20 p-3 md:grid-cols-6">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-16" />
                </div>
            ))}
        </div>
    );
}
