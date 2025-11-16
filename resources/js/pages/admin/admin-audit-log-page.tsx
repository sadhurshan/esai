import { useMemo, useState, type FormEvent } from 'react';

import Heading from '@/components/heading';
import { AuditLogTable } from '@/components/admin/audit-log-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/contexts/auth-context';
import { useAuditLog } from '@/hooks/api/admin/use-audit-log';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { AuditLogFilters } from '@/types/admin';

const DEFAULT_PAGE_SIZE = 25;

export function AdminAuditLogPage() {
    const { isAdmin } = useAuth();
    const [filters, setFilters] = useState<AuditLogFilters>({ perPage: DEFAULT_PAGE_SIZE });
    const [formValues, setFormValues] = useState({
        actor: '',
        event: '',
        resource: '',
        from: '',
        to: '',
    });

    const { data, isLoading } = useAuditLog(filters, { enabled: isAdmin });

    const entries = data?.items ?? [];
    const meta = data?.meta;

    const handleInputChange = (field: keyof typeof formValues, value: string) => {
        setFormValues((prev) => ({ ...prev, [field]: value }));
    };

    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setFilters((prev) => ({
            ...prev,
            cursor: null,
            actor: normalizeFilter(formValues.actor),
            event: normalizeFilter(formValues.event),
            resource: normalizeFilter(formValues.resource),
            from: normalizeFilter(formValues.from),
            to: normalizeFilter(formValues.to),
        }));
    };

    const clearFilters = () => {
        setFormValues({ actor: '', event: '', resource: '', from: '', to: '' });
        setFilters({ perPage: DEFAULT_PAGE_SIZE });
    };

    const handleCursorChange = (cursor: string | null) => {
        setFilters((prev) => ({ ...prev, cursor }));
    };

    const exportDisabled = entries.length === 0;

    const exportLabel = useMemo(() => {
        if (exportDisabled) {
            return 'No rows to export';
        }
        return `Export ${entries.length} rows`;
    }, [entries.length, exportDisabled]);

    const handleExport = () => {
        if (exportDisabled || typeof window === 'undefined') {
            return;
        }
        const header = ['timestamp', 'actor_name', 'actor_email', 'event', 'resource', 'ip_address', 'metadata'];
        const rows = entries.map((entry) => {
            const resourceLabel = entry.resource ? `${entry.resource.type}#${entry.resource.id}` : '';
            const metadata = entry.metadata ? JSON.stringify(entry.metadata) : '';
            const actorName = entry.actor?.name ?? 'System event';
            const actorEmail = entry.actor?.email ?? (entry.actor?.id ? `User #${entry.actor.id}` : '');
            return [
                entry.timestamp,
                actorName,
                actorEmail,
                entry.event,
                resourceLabel,
                entry.ipAddress ?? '',
                metadata,
            ];
        });
        const csv = [header, ...rows]
            .map((columns) => columns.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(','))
            .join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `audit-log-${Date.now()}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    }; 

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="Audit log"
                    description="Interrogate privileged actions across the tenant with full filtering and export options."
                />
                <Button type="button" variant="outline" disabled={exportDisabled} onClick={handleExport}>
                    {exportDisabled ? 'Export CSV' : exportLabel}
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Filters</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={applyFilters}>
                        <div className="space-y-2">
                            <Label htmlFor="audit-actor">Actor</Label>
                            <Input
                                id="audit-actor"
                                placeholder="user@example.com"
                                value={formValues.actor}
                                onChange={(event) => handleInputChange('actor', event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="audit-event">Event</Label>
                            <Input
                                id="audit-event"
                                placeholder="admin.role.updated"
                                value={formValues.event}
                                onChange={(event) => handleInputChange('event', event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="audit-resource">Resource</Label>
                            <Input
                                id="audit-resource"
                                placeholder="company:42"
                                value={formValues.resource}
                                onChange={(event) => handleInputChange('resource', event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="audit-from">From</Label>
                            <Input
                                id="audit-from"
                                type="datetime-local"
                                value={formValues.from}
                                onChange={(event) => handleInputChange('from', event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="audit-to">To</Label>
                            <Input
                                id="audit-to"
                                type="datetime-local"
                                value={formValues.to}
                                onChange={(event) => handleInputChange('to', event.target.value)}
                            />
                        </div>
                        <div className="flex items-end gap-2">
                            <Button type="button" variant="outline" onClick={clearFilters}>
                                Clear
                            </Button>
                            <Button type="submit">Apply</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <AuditLogTable
                entries={entries}
                meta={meta}
                isLoading={isLoading}
                onNextPage={(cursor) => handleCursorChange(cursor)}
                onPrevPage={(cursor) => handleCursorChange(cursor)}
            />
        </div>
    );
}

function normalizeFilter(value?: string | null) {
    const trimmed = value?.trim();
    return trimmed ? trimmed : undefined;
}
