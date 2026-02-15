import { FormEvent, useMemo, useState } from 'react';

import { DataTable, type DataTableColumn } from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useAiEvents } from '@/hooks/api/admin/use-ai-events';
import { cn } from '@/lib/utils';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { AiEventEntry, AiEventFilters } from '@/types/admin';

const DEFAULT_PAGE_SIZE = 25;
const STATUS_ANY_VALUE = '__any';
const STATUS_OPTIONS = [
    { label: 'Any status', value: STATUS_ANY_VALUE },
    { label: 'Success', value: 'success' },
    { label: 'Error', value: 'error' },
];

export function AdminAiActivityLogPage() {
    const { isAdmin } = useAuth();
    const { formatDate } = useFormatting();
    const [filters, setFilters] = useState<AiEventFilters>({
        perPage: DEFAULT_PAGE_SIZE,
    });
    const [formValues, setFormValues] = useState({
        feature: '',
        status: STATUS_ANY_VALUE,
        entity: '',
        from: '',
        to: '',
    });

    const { data, isLoading } = useAiEvents(filters, { enabled: isAdmin });

    const entries = data?.items ?? [];
    const meta = data?.meta;
    const nextCursor = meta?.nextCursor ?? null;
    const prevCursor = meta?.prevCursor ?? null;
    const exportDisabled = entries.length === 0;

    const columns = useMemo<DataTableColumn<AiEventEntry>[]>(
        () => [
            {
                key: 'timestamp',
                title: 'Timestamp',
                render: (row) => (
                    <div className="flex flex-col">
                        <span className="text-sm font-semibold text-foreground">
                            {formatDate(row.timestamp, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            }) ?? '—'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {formatDate(row.timestamp, {
                                dateStyle: 'full',
                                timeStyle: 'medium',
                            }) ?? ''}
                        </span>
                    </div>
                ),
            },
            {
                key: 'user',
                title: 'User',
                render: (row) => {
                    const actorName = row.user?.name ?? 'System event';
                    const actorDetail = row.user?.email
                        ? row.user.email
                        : row.user?.id
                          ? `User #${row.user.id}`
                          : '—';
                    return (
                        <div className="flex flex-col">
                            <span className="font-medium text-foreground">
                                {actorName}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {actorDetail}
                            </span>
                        </div>
                    );
                },
            },
            {
                key: 'feature',
                title: 'Feature',
                render: (row) => (
                    <div className="flex flex-col text-sm">
                        <span className="font-medium text-foreground">
                            {row.feature ?? '—'}
                        </span>
                        {row.error_message ? (
                            <span className="text-xs text-destructive">
                                {row.error_message}
                            </span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'entity',
                title: 'Entity',
                render: (row) => {
                    if (!row.entity) {
                        return '—';
                    }
                    const label =
                        row.entity.label ??
                        `${row.entity.type ?? ''} #${row.entity.id ?? '—'}`;
                    return (
                        <div className="flex flex-col text-sm">
                            <span className="font-medium text-foreground">
                                {row.entity.type ?? 'Entity'} #
                                {row.entity.id ?? '—'}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {label}
                            </span>
                        </div>
                    );
                },
            },
            {
                key: 'status',
                title: 'Status',
                render: (row) => (
                    <Badge
                        variant="outline"
                        className={cn(
                            'tracking-wide uppercase',
                            statusBadgeVariant(row.status),
                        )}
                    >
                        {row.status ?? 'unknown'}
                    </Badge>
                ),
            },
            {
                key: 'latency_ms',
                title: 'Latency',
                render: (row) => formatLatency(row.latency_ms),
                align: 'right',
            },
        ],
        [formatDate],
    );

    const exportLabel = useMemo(() => {
        if (exportDisabled) {
            return 'No rows to export';
        }
        return `Export ${entries.length} rows`;
    }, [entries.length, exportDisabled]);

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const handleInputChange = (
        field: keyof typeof formValues,
        value: string,
    ) => {
        setFormValues((prev) => ({ ...prev, [field]: value }));
    };

    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const normalizedStatus =
            formValues.status === STATUS_ANY_VALUE ? '' : formValues.status;

        setFilters((prev) => ({
            ...prev,
            cursor: null,
            feature: normalizeFilter(formValues.feature),
            status: normalizeFilter(normalizedStatus),
            entity: normalizeFilter(formValues.entity),
            from: normalizeFilter(formValues.from),
            to: normalizeFilter(formValues.to),
        }));
    };

    const clearFilters = () => {
        setFormValues({
            feature: '',
            status: STATUS_ANY_VALUE,
            entity: '',
            from: '',
            to: '',
        });
        setFilters({ perPage: DEFAULT_PAGE_SIZE });
    };

    const handleCursorChange = (cursor: string | null) => {
        setFilters((prev) => ({ ...prev, cursor }));
    };

    const handleExport = () => {
        if (exportDisabled || typeof window === 'undefined') {
            return;
        }

        const header = [
            'timestamp',
            'user_name',
            'user_email',
            'feature',
            'entity',
            'status',
            'latency_ms',
        ];
        const rows = entries.map((entry) => {
            const entityLabel = entry.entity
                ? `${entry.entity.type ?? 'Entity'}#${entry.entity.id ?? '—'}`
                : '';
            return [
                entry.timestamp ?? '',
                entry.user?.name ?? 'System event',
                entry.user?.email ??
                    (entry.user?.id ? `User #${entry.user.id}` : ''),
                entry.feature ?? '',
                entityLabel,
                entry.status ?? '',
                entry.latency_ms ?? '',
            ];
        });

        const csv = [header, ...rows]
            .map((row) =>
                row
                    .map(
                        (value) =>
                            `"${String(value ?? '').replace(/"/g, '""')}"`,
                    )
                    .join(','),
            )
            .join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `ai-events-${Date.now()}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="AI activity log"
                    description="Track every AI request across tenants, including latency, entity context, and status."
                />
                <Button
                    type="button"
                    variant="outline"
                    disabled={exportDisabled}
                    onClick={handleExport}
                >
                    {exportDisabled ? 'Export CSV' : exportLabel}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Filters</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                        onSubmit={applyFilters}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="ai-feature">Feature</Label>
                            <Input
                                id="ai-feature"
                                placeholder="supplier_risk"
                                value={formValues.feature}
                                onChange={(event) =>
                                    handleInputChange(
                                        'feature',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="ai-status">Status</Label>
                            <Select
                                value={formValues.status}
                                onValueChange={(value) =>
                                    handleInputChange('status', value)
                                }
                            >
                                <SelectTrigger id="ai-status">
                                    <SelectValue placeholder="Any status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="ai-entity">Entity</Label>
                            <Input
                                id="ai-entity"
                                placeholder="RFQ:123"
                                value={formValues.entity}
                                onChange={(event) =>
                                    handleInputChange(
                                        'entity',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="ai-from">From</Label>
                            <Input
                                id="ai-from"
                                type="datetime-local"
                                value={formValues.from}
                                onChange={(event) =>
                                    handleInputChange(
                                        'from',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="ai-to">To</Label>
                            <Input
                                id="ai-to"
                                type="datetime-local"
                                value={formValues.to}
                                onChange={(event) =>
                                    handleInputChange('to', event.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={clearFilters}
                            >
                                Clear
                            </Button>
                            <Button type="submit">Apply</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <DataTable
                data={entries}
                columns={columns}
                isLoading={isLoading}
                emptyState={
                    <div className="text-sm text-muted-foreground">
                        No AI events match your filters. Trigger an AI action or
                        adjust filters to populate the log.
                    </div>
                }
            />

            {entries.length > 0 ? (
                <div className="flex flex-col gap-3 rounded-xl border bg-muted/30 p-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                    <span>
                        {entries.length} result{entries.length === 1 ? '' : 's'}
                    </span>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={!prevCursor}
                            onClick={() => handleCursorChange(prevCursor)}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={!nextCursor}
                            onClick={() => handleCursorChange(nextCursor)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function normalizeFilter(value?: string | null) {
    const trimmed = value?.trim();
    return trimmed ? trimmed : undefined;
}

function formatLatency(value?: number | null) {
    if (value === null || value === undefined) {
        return '—';
    }
    return `${value.toLocaleString()} ms`;
}

function statusBadgeVariant(status?: string | null) {
    if (!status) {
        return '';
    }
    const normalized = status.toLowerCase();
    if (normalized === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }
    if (normalized === 'error') {
        return 'border-destructive/40 bg-destructive/10 text-destructive';
    }
    return 'border-muted-foreground/40 text-muted-foreground';
}
