import { Eye, ShieldAlert } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/format';
import type { OffsetPaginationMeta } from '@/lib/pagination';
import { cn } from '@/lib/utils';
import type { CompanyApprovalItem } from '@/types/admin';

const moderationStatuses = new Set(['pending', 'pending_verification']);

export interface CompanyApprovalTableProps {
    companies: CompanyApprovalItem[];
    meta?: OffsetPaginationMeta;
    isLoading?: boolean;
    approvingId?: number | null;
    rejectingId?: number | null;
    onApprove?: (company: CompanyApprovalItem) => void;
    onReject?: (company: CompanyApprovalItem) => void;
    onPageChange?: (page: number) => void;
    onView?: (company: CompanyApprovalItem) => void;
}

export function CompanyApprovalTable({
    companies,
    meta,
    isLoading = false,
    approvingId,
    rejectingId,
    onApprove,
    onReject,
    onPageChange,
    onView,
}: CompanyApprovalTableProps) {
    if (isLoading) {
        return <CompanyApprovalSkeleton />;
    }

    if (!companies.length) {
        return (
            <EmptyState
                icon={<ShieldAlert className="h-10 w-10" aria-hidden />}
                title="No companies in this queue"
                description="Switch filters or wait for new registrations to review."
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
                        <th className="px-4 py-3 font-semibold">Primary contact</th>
                        <th className="px-4 py-3 font-semibold">Status</th>
                        <th className="px-4 py-3 font-semibold">Region</th>
                        <th className="px-4 py-3 font-semibold">Submitted</th>
                        <th className="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-muted bg-background">
                    {companies.map((company) => {
                        const eligible = isCompanyModerationEligible(company.status);
                        const awaitingApproval = approvingId === company.id;
                        const awaitingRejection = rejectingId === company.id;
                        const onboardingComplete = Boolean(company.has_completed_onboarding);

                        return (
                            <tr key={company.id} className="align-top">
                                <td className="px-4 py-4">
                                    <div className="flex flex-col gap-1">
                                        <span className="font-semibold text-foreground">{company.name}</span>
                                        <span className="text-xs text-muted-foreground">Slug: {company.slug}</span>
                                        {company.email_domain ? (
                                            <span className="text-xs text-muted-foreground">Domain: {company.email_domain}</span>
                                        ) : null}
                                    </div>
                                </td>
                                <td className="px-4 py-4">
                                    <div className="flex flex-col gap-1 text-sm">
                                        <span className="font-medium text-foreground">
                                            {company.primary_contact_name ?? '—'}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {company.primary_contact_email ?? 'No email on file'}
                                        </span>
                                        {company.primary_contact_phone ? (
                                            <span className="text-xs text-muted-foreground">{company.primary_contact_phone}</span>
                                        ) : null}
                                    </div>
                                </td>
                                <td className="px-4 py-4">
                                    <div className="flex flex-col gap-2">
                                        <Badge variant={companyStatusVariant(company.status)} className="w-fit">
                                            {companyStatusLabel(company.status)}
                                        </Badge>
                                        {onboardingComplete ? (
                                            <Badge variant="outline" className="w-fit text-[11px]">
                                                Onboarding complete
                                            </Badge>
                                        ) : null}
                                        {company.rejection_reason ? (
                                            <p className="text-xs text-muted-foreground">Reason: {company.rejection_reason}</p>
                                        ) : null}
                                    </div>
                                </td>
                                <td className="px-4 py-4 text-sm text-muted-foreground">
                                    <div className="flex flex-col">
                                        <span>{company.region ?? '—'}</span>
                                        <span>{company.country ?? '—'}</span>
                                    </div>
                                </td>
                                <td className="px-4 py-4 text-sm text-muted-foreground">
                                    <div className="flex flex-col">
                                        <span className="font-medium text-foreground">
                                            {formatDate(company.created_at)}
                                        </span>
                                        <span className="text-xs">Updated {formatDate(company.updated_at)}</span>
                                    </div>
                                </td>
                                <td className="px-4 py-4">
                                    <div className="flex flex-col gap-2 text-right sm:flex-row sm:justify-end">
                                        {onView ? (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                className="gap-1"
                                                onClick={() => onView(company)}
                                            >
                                                <Eye className="h-4 w-4" aria-hidden /> View details
                                            </Button>
                                        ) : null}
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={!eligible || awaitingApproval || awaitingRejection}
                                            onClick={() => onApprove?.(company)}
                                        >
                                            {awaitingApproval ? 'Approving…' : 'Approve'}
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={!eligible || awaitingRejection || awaitingApproval}
                                            onClick={() => onReject?.(company)}
                                            className={cn('border-destructive/40 text-destructive hover:bg-destructive/10')}
                                        >
                                            {awaitingRejection ? 'Rejecting…' : 'Reject'}
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            <div className="flex flex-col gap-3 border-t bg-muted/20 p-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>
                    Page {currentPage}
                    {lastPage ? ` of ${lastPage}` : ''}
                    {typeof total === 'number' ? ` • ${total} companies` : ''}
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

export function isCompanyModerationEligible(status?: string | null): boolean {
    if (!status) {
        return false;
    }

    return moderationStatuses.has(status.toLowerCase());
}

export function companyStatusLabel(status?: string | null): string {
    if (!status) {
        return 'Unknown';
    }

    const normalized = status.replace(/_/g, ' ');
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

export function companyStatusVariant(status?: string | null): 'default' | 'secondary' | 'outline' {
    switch (status) {
        case 'pending':
            return 'outline';
        case 'pending_verification':
            return 'secondary';
        case 'rejected':
            return 'outline';
        default:
            return 'default';
    }
}

function CompanyApprovalSkeleton() {
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
