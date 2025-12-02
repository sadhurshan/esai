import { useMemo, useState, type ReactNode } from 'react';
import { Factory, Files } from 'lucide-react';

import Heading from '@/components/heading';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { useAuth } from '@/contexts/auth-context';
import { useAdminSupplierApplications } from '@/hooks/api/admin/use-supplier-applications';
import { useSupplierApplicationAuditLogs } from '@/hooks/api/admin/use-supplier-application-audit-logs';
import { useApproveSupplierApplication } from '@/hooks/api/admin/use-approve-supplier-application';
import { useRejectSupplierApplication } from '@/hooks/api/admin/use-reject-supplier-application';
import { formatDate } from '@/lib/format';
import type { OffsetPaginationMeta } from '@/lib/pagination';
import type {
    SupplierApplicationFilters,
    SupplierApplicationItem,
    SupplierApplicationFormPayload,
    SupplierApplicationStatusValue,
} from '@/types/admin';

const DEFAULT_STATUS: StatusFilterValue = 'pending';
const PAGE_SIZE = 25;

const PLATFORM_ROLES = new Set(['platform_super', 'platform_support']);

type StatusFilterValue = SupplierApplicationStatusValue | 'all';

const STATUS_FILTERS: Array<{ label: string; value: StatusFilterValue; description: string }> = [
    {
        label: 'Pending review',
        value: 'pending',
        description: 'Awaiting compliance review and supplier activation.',
    },
    {
        label: 'Approved',
        value: 'approved',
        description: 'Completed reviews resulting in supplier activation.',
    },
    {
        label: 'Rejected',
        value: 'rejected',
        description: 'Declined submissions pending re-work from the tenant.',
    },
    {
        label: 'All',
        value: 'all',
        description: 'Every supplier application regardless of status.',
    },
];

export function AdminSupplierApplicationsPage() {
    const { state } = useAuth();
    const role = state.user?.role ?? null;
    const isPlatformOperator = Boolean(role && PLATFORM_ROLES.has(role));

    const [status, setStatus] = useState<StatusFilterValue>(DEFAULT_STATUS);
    const [page, setPage] = useState(1);
    const [selectedApplication, setSelectedApplication] = useState<SupplierApplicationItem | null>(null);
    const [reviewNotes, setReviewNotes] = useState('');

    const queryParams = useMemo<SupplierApplicationFilters>(
        () => ({ status, page, perPage: PAGE_SIZE }),
        [status, page],
    );

    const { data, isLoading } = useAdminSupplierApplications(queryParams);
    const approveMutation = useApproveSupplierApplication();
    const rejectMutation = useRejectSupplierApplication();

    const applications = data?.items ?? [];
    const pagination = data?.meta;
    const activeFilter = STATUS_FILTERS.find((filter) => filter.value === status) ?? STATUS_FILTERS[0];
    const auditLogsQuery = useSupplierApplicationAuditLogs(selectedApplication?.id ?? null, {
        enabled: Boolean(selectedApplication),
        limit: 25,
    });
    const auditLogs = auditLogsQuery.data ?? [];

    if (!isPlatformOperator) {
        return <AccessDeniedPage />;
    }

    const openReviewPanel = (application: SupplierApplicationItem) => {
        setSelectedApplication(application);
        setReviewNotes(application.notes ?? '');
    };

    const closeReviewPanel = () => {
        setSelectedApplication(null);
        setReviewNotes('');
    };

    const handleStatusChange = (value: string) => {
        if (value === status) {
            return;
        }
        setStatus(value as StatusFilterValue);
        setPage(1);
    };

    const handleApproveSelected = () => {
        if (!selectedApplication) {
            return;
        }
        const trimmedNotes = reviewNotes.trim();
        approveMutation.mutate(
            { applicationId: selectedApplication.id, notes: trimmedNotes || null },
            {
                onSuccess: () => {
                    closeReviewPanel();
                },
            },
        );
    };

    const handleRejectSelected = () => {
        if (!selectedApplication) {
            return;
        }
        const trimmedNotes = reviewNotes.trim();
        if (trimmedNotes.length < 5) {
            return;
        }
        rejectMutation.mutate(
            { applicationId: selectedApplication.id, notes: trimmedNotes },
            {
                onSuccess: () => {
                    closeReviewPanel();
                },
            },
        );
    };

    const decisionDisabled = selectedApplication?.status !== 'pending';
    const rejectDisabled = decisionDisabled || reviewNotes.trim().length < 5 || rejectMutation.isPending;
    const approveDisabled = decisionDisabled || approveMutation.isPending;

    return (
        <div className="space-y-8">
            <Heading
                title="Supplier applications"
                description="Review tenant submissions, verify compliance documents, and control supplier activation."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Queues</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Tabs value={status} defaultValue={DEFAULT_STATUS} onValueChange={handleStatusChange}>
                        <TabsList className="grid gap-2 sm:grid-cols-4">
                            {STATUS_FILTERS.map((filter) => (
                                <TabsTrigger key={filter.value} value={filter.value} className="text-sm">
                                    {filter.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                    <p className="text-sm text-muted-foreground">{activeFilter.description}</p>
                </CardContent>
            </Card>

            <SupplierApplicationsTable
                items={applications}
                meta={pagination}
                isLoading={isLoading}
                onReview={openReviewPanel}
                onPageChange={setPage}
            />

            <Sheet open={Boolean(selectedApplication)} onOpenChange={(open) => (!open ? closeReviewPanel() : null)}>
                <SheetContent className="flex flex-col gap-6 overflow-y-auto sm:max-w-3xl">
                    {selectedApplication ? (
                        <>
                            <SheetHeader>
                                <SheetTitle>
                                    {selectedApplication.company?.name ?? `Application #${selectedApplication.id}`}
                                </SheetTitle>
                                <SheetDescription>
                                    Submitted {formatDate(selectedApplication.created_at)} • Status {statusLabel(selectedApplication.status)}
                                </SheetDescription>
                            </SheetHeader>

                            <DetailSection title="Company overview">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <InfoBlock label="Registration">
                                        <p className="text-sm text-foreground">
                                            {selectedApplication.company?.registration_no ?? 'Not provided'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Tax ID: {selectedApplication.company?.tax_id ?? '—'}
                                        </p>
                                    </InfoBlock>
                                    <InfoBlock label="Primary contact">
                                        <p className="text-sm font-medium text-foreground">
                                            {selectedApplication.company?.primary_contact_name ?? '—'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedApplication.company?.primary_contact_email ?? 'No email on file'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedApplication.company?.primary_contact_phone ?? 'No phone on file'}
                                        </p>
                                    </InfoBlock>
                                    <InfoBlock label="Location">
                                        <p className="text-sm text-foreground">
                                            {formatLocation(selectedApplication.form_json, selectedApplication.company)}
                                        </p>
                                    </InfoBlock>
                                    <InfoBlock label="Website">
                                        {selectedApplication.form_json?.website ? (
                                            <a
                                                href={selectedApplication.form_json.website}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-sm text-primary underline"
                                            >
                                                {selectedApplication.form_json.website}
                                            </a>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">Not provided</p>
                                        )}
                                    </InfoBlock>
                                </div>
                            </DetailSection>

                            <DetailSection title="Capabilities & production data">
                                <div className="grid gap-4">
                                    <InfoBlock label="Capabilities">
                                        <CapabilityList capabilities={selectedApplication.form_json?.capabilities} />
                                    </InfoBlock>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <InfoBlock label="Minimum order quantity">
                                            <p className="text-sm text-foreground">
                                                {formatMetric(
                                                    selectedApplication.form_json?.min_order_qty ??
                                                        selectedApplication.form_json?.moq,
                                                )}
                                            </p>
                                        </InfoBlock>
                                        <InfoBlock label="Lead time (days)">
                                            <p className="text-sm text-foreground">
                                                {formatMetric(selectedApplication.form_json?.lead_time_days)}
                                            </p>
                                        </InfoBlock>
                                        <InfoBlock label="Certifications">
                                            <TagList items={selectedApplication.form_json?.certifications} emptyLabel="None" />
                                        </InfoBlock>
                                        <InfoBlock label="Facilities">
                                            <p className="text-sm text-foreground">
                                                {selectedApplication.form_json?.facilities ?? 'Not provided'}
                                            </p>
                                        </InfoBlock>
                                    </div>
                                    {selectedApplication.form_json?.notes ? (
                                        <InfoBlock label="Applicant notes">
                                            <p className="text-sm text-foreground">
                                                {selectedApplication.form_json.notes}
                                            </p>
                                        </InfoBlock>
                                    ) : null}
                                </div>
                            </DetailSection>

                            <DetailSection title="Compliance documents">
                                {selectedApplication.documents && selectedApplication.documents.length > 0 ? (
                                    <div className="space-y-3">
                                        {selectedApplication.documents.map((document) => (
                                            <Card key={document.id} className="border-muted">
                                                <CardContent className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p className="font-medium text-foreground">
                                                            {document.type?.toUpperCase() ?? 'Document'}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {document.mime ?? 'application/octet-stream'} · {formatFileSize(document.size_bytes)}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Issued {formatDate(document.issued_at)} • Expires {formatDate(document.expires_at)}
                                                        </p>
                                                    </div>
                                                    <div className="flex flex-col gap-2 sm:items-end">
                                                        <Badge variant={documentStatusVariant(document.status)} className="w-fit">
                                                            {statusLabel(document.status)}
                                                        </Badge>
                                                        {document.download_url ? (
                                                            <Button asChild size="sm" variant="outline">
                                                                <a
                                                                    href={document.download_url}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    View file
                                                                </a>
                                                            </Button>
                                                        ) : null}
                                                    </div>
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        icon={<Files className="h-8 w-8" aria-hidden />}
                                        title="No documents attached"
                                        description="The applicant did not include supporting compliance files."
                                        className="py-6"
                                    />
                                )}
                            </DetailSection>

                            {selectedApplication.notes ? (
                                <DetailSection title="Reviewer notes">
                                    <p className="text-sm text-muted-foreground">{selectedApplication.notes}</p>
                                </DetailSection>
                            ) : null}

                            <DetailSection title="Audit history">
                                {auditLogsQuery.isLoading ? (
                                    <AuditTimelineSkeleton />
                                ) : auditLogs.length === 0 ? (
                                    <EmptyState
                                        icon={<Files className="h-8 w-8" aria-hidden />}
                                        title="No audit entries"
                                        description="No changes have been recorded for this application yet."
                                        className="py-6"
                                    />
                                ) : (
                                    <div className="space-y-3">
                                        {auditLogs.map((log) => {
                                            const changedFields = resolveAuditFields(log);

                                            return (
                                                <div key={log.id} className="space-y-2 rounded-lg border bg-background/80 p-3">
                                                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                                        <p className="text-sm font-medium capitalize text-foreground">{log.event}</p>
                                                        <span className="text-xs text-muted-foreground">{formatDateTime(log.timestamp)}</span>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">{formatActor(log.actor)}</p>
                                                    {changedFields.length > 0 ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            Fields: {changedFields.join(', ')}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </DetailSection>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Decision</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {decisionDisabled ? (
                                        <div className="rounded-md border border-muted bg-muted/50 p-3 text-sm text-muted-foreground">
                                            This application is already {statusLabel(selectedApplication.status)}.
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Provide reviewer notes to include in the approval or rejection response sent to the tenant.
                                        </p>
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="review-notes">Notes to applicant</Label>
                                        <Textarea
                                            id="review-notes"
                                            rows={4}
                                            placeholder="Summarize findings, missing artifacts, or next steps."
                                            value={reviewNotes}
                                            onChange={(event) => setReviewNotes(event.target.value)}
                                            disabled={decisionDisabled}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Rejections require at least 5 characters.
                                        </p>
                                    </div>

                                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            disabled={rejectDisabled}
                                            onClick={handleRejectSelected}
                                        >
                                            {rejectMutation.isPending ? 'Rejecting…' : 'Reject supplier'}
                                        </Button>
                                        <Button type="button" disabled={approveDisabled} onClick={handleApproveSelected}>
                                            {approveMutation.isPending ? 'Approving…' : 'Approve supplier'}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </>
                    ) : null}
                </SheetContent>
            </Sheet>
        </div>
    );
}

interface SupplierApplicationsTableProps {
    items: SupplierApplicationItem[];
    meta?: OffsetPaginationMeta;
    isLoading?: boolean;
    onReview: (application: SupplierApplicationItem) => void;
    onPageChange?: (page: number) => void;
}

function SupplierApplicationsTable({ items, meta, isLoading = false, onReview, onPageChange }: SupplierApplicationsTableProps) {
    if (isLoading) {
        return <SupplierApplicationsSkeleton />;
    }

    if (!items.length) {
        return (
            <EmptyState
                icon={<Factory className="h-10 w-10" aria-hidden />}
                title="No applications"
                description="There are no supplier applications in this queue."
            />
        );
    }

    const currentPage = meta?.currentPage ?? 1;
    const lastPage = meta?.lastPage ?? currentPage;
    const total = meta?.total;
    const hasPrev = currentPage > 1;
    const hasNext = lastPage ? currentPage < lastPage : false;

    return (
        <div className="overflow-hidden rounded-xl border">
            <table className="min-w-full divide-y divide-muted text-sm">
                <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th className="px-4 py-3 font-semibold">Company</th>
                        <th className="px-4 py-3 font-semibold">Capabilities</th>
                        <th className="px-4 py-3 font-semibold">Location</th>
                        <th className="px-4 py-3 font-semibold">Documents</th>
                        <th className="px-4 py-3 font-semibold">Status</th>
                        <th className="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-muted bg-background">
                    {items.map((application) => (
                        <tr key={application.id} className="align-top">
                            <td className="px-4 py-4">
                                <div className="flex flex-col gap-1">
                                    <span className="font-semibold text-foreground">
                                        {application.company?.name ?? `Company #${application.company_id}`}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Submitted {formatDate(application.created_at)}
                                    </span>
                                </div>
                            </td>
                            <td className="px-4 py-4 text-sm text-muted-foreground">
                                {summarizeCapabilities(application.form_json)}
                            </td>
                            <td className="px-4 py-4 text-sm text-muted-foreground">
                                {formatLocation(application.form_json, application.company)}
                            </td>
                            <td className="px-4 py-4 text-sm">
                                <Badge variant="outline" className="text-xs">
                                    {application.documents?.length ?? 0} files
                                </Badge>
                            </td>
                            <td className="px-4 py-4">
                                <Badge variant={statusVariant(application.status)}>{statusLabel(application.status)}</Badge>
                            </td>
                            <td className="px-4 py-4 text-right">
                                <Button type="button" size="sm" variant="outline" onClick={() => onReview(application)}>
                                    Review
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="flex flex-col gap-3 border-t bg-muted/20 p-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>
                    Page {currentPage}
                    {lastPage ? ` of ${lastPage}` : ''}
                    {typeof total === 'number' ? ` • ${total} applications` : ''}
                </span>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" size="sm" disabled={!hasPrev} onClick={() => hasPrev && onPageChange?.(currentPage - 1)}>
                        Previous
                    </Button>
                    <Button type="button" variant="outline" size="sm" disabled={!hasNext} onClick={() => hasNext && onPageChange?.(currentPage + 1)}>
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}

function SupplierApplicationsSkeleton() {
    return (
        <div className="space-y-2 rounded-xl border p-4">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="grid gap-3 rounded-lg border bg-muted/20 p-3 md:grid-cols-6">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    );
}

function statusLabel(value?: string | null): string {
    if (!value) {
        return 'Unknown';
    }
    const normalized = value.replace(/_/g, ' ');
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function statusVariant(value?: string | null): 'default' | 'secondary' | 'outline' {
    switch (value) {
        case 'pending':
            return 'outline';
        case 'approved':
            return 'default';
        case 'rejected':
            return 'secondary';
        default:
            return 'outline';
    }
}

function documentStatusVariant(value?: string | null): 'default' | 'secondary' | 'outline' {
    switch (value) {
        case 'valid':
            return 'default';
        case 'expiring':
            return 'secondary';
        case 'expired':
            return 'outline';
        default:
            return 'outline';
    }
}

function summarizeCapabilities(form?: SupplierApplicationFormPayload | null): string {
    if (!form || !form.capabilities) {
        return '—';
    }
    const lists = Object.values(form.capabilities)
        .filter((value): value is string[] => Array.isArray(value))
        .flatMap((value) => value)
        .filter((value): value is string => typeof value === 'string' && value.trim().length > 0);

    if (!lists.length) {
        return '—';
    }

    if (lists.length <= 3) {
        return lists.join(', ');
    }

    return `${lists.slice(0, 3).join(', ')} +${lists.length - 3} more`;
}

function formatLocation(form?: SupplierApplicationFormPayload | null, company?: SupplierApplicationItem['company']): string {
    const city = form?.city ?? company?.region ?? null;
    const country = form?.country ?? company?.country ?? null;

    if (!city && !country) {
        return '—';
    }

    if (city && country) {
        return `${city}, ${country}`;
    }

    return city ?? country ?? '—';
}

function formatMetric(value?: number | null): string {
    if (typeof value === 'number' && Number.isFinite(value) && value > 0) {
        return value.toLocaleString();
    }
    return 'Not provided';
}

function formatFileSize(bytes?: number | null): string {
    if (!bytes || Number.isNaN(bytes)) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }
    return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '—';
    }
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }
    return parsed.toLocaleString();
}

function formatActor(actor?: { name?: string | null; email?: string | null } | null): string {
    if (!actor) {
        return 'System';
    }
    if (actor.name && actor.email) {
        return `${actor.name} (${actor.email})`;
    }
    return actor.name ?? actor.email ?? 'System';
}

function resolveAuditFields(log: { metadata?: Record<string, unknown> | null }): string[] {
    const metadata = log.metadata as { after?: Record<string, unknown> } | undefined;
    const after = metadata?.after;
    if (!after || typeof after !== 'object') {
        return [];
    }
    return Object.keys(after).slice(0, 5);
}

function AuditTimelineSkeleton() {
    return (
        <div className="space-y-3">
            {[0, 1, 2].map((index) => (
                <div key={index} className="space-y-2 rounded-lg border bg-background/80 p-3">
                    <Skeleton className="h-4 w-1/3" />
                    <Skeleton className="h-3 w-1/4" />
                    <Skeleton className="h-3 w-1/2" />
                </div>
            ))}
        </div>
    );
}

function CapabilityList({ capabilities }: { capabilities?: SupplierApplicationFormPayload['capabilities'] }) {
    if (!capabilities) {
        return <p className="text-sm text-muted-foreground">Not provided</p>;
    }

    const entries = Object.entries(capabilities)
        .map(([key, value]) => ({
            key,
            values: Array.isArray(value)
                ? value.filter((item): item is string => typeof item === 'string' && item.trim().length > 0)
                : [],
        }))
        .filter((entry) => entry.values.length > 0);

    if (!entries.length) {
        return <p className="text-sm text-muted-foreground">Not provided</p>;
    }

    return (
        <div className="space-y-3">
            {entries.map((entry) => (
                <div key={entry.key} className="space-y-1">
                    <p className="text-xs font-semibold uppercase text-muted-foreground">{formatCapabilityLabel(entry.key)}</p>
                    <div className="flex flex-wrap gap-2">
                        {entry.values.map((value) => (
                            <Badge key={`${entry.key}-${value}`} variant="secondary">
                                {value}
                            </Badge>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function formatCapabilityLabel(key: string): string {
    if (!key) {
        return 'Capabilities';
    }
    const withSpaces = key.replace(/_/g, ' ');
    return withSpaces.charAt(0).toUpperCase() + withSpaces.slice(1);
}

function TagList({ items, emptyLabel = 'Not provided' }: { items?: string[] | null; emptyLabel?: string }) {
    if (!items || !items.length) {
        return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {items.map((item) => (
                <Badge key={item} variant="outline">
                    {item}
                </Badge>
            ))}
        </div>
    );
}

function DetailSection({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="space-y-3">
            <h3 className="text-base font-semibold text-foreground">{title}</h3>
            <div className="rounded-lg border bg-muted/30 p-4">{children}</div>
        </section>
    );
}

function InfoBlock({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
            {children}
        </div>
    );
}
