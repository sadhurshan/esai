import { AlertTriangle, FileSearch, LayoutGrid } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { MoneyCell } from '@/components/quotes/money-cell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useRfp } from '@/hooks/api/rfps/use-rfp';
import { useRfpProposals } from '@/hooks/api/rfps/use-rfp-proposals';
import { cn } from '@/lib/utils';
import type { RfpProposalSummary } from '@/types/rfp';

export function RfpProposalReviewPage() {
    const navigate = useNavigate();
    const params = useParams<{ rfpId?: string }>();
    const { formatDate } = useFormatting();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const rfpsEnabled =
        hasFeature('rfps_enabled') ||
        hasFeature('projects_enabled') ||
        hasFeature('sourcing_access');

    const rfpId = params.rfpId ?? '';

    const rfpQuery = useRfp(rfpId, { enabled: Boolean(rfpId) });
    const proposalsQuery = useRfpProposals(rfpId, { enabled: Boolean(rfpId) });

    const proposals = proposalsQuery.data?.items ?? [];
    const summary = proposalsQuery.data?.summary;
    const highlightPriceId = determineLowestPriceProposal(proposals);
    const highlightLeadId = determineFastestProposal(proposals);

    if (featureFlagsLoaded && !rfpsEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Proposal comparison</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="RFP workspace unavailable"
                    description="Upgrade your plan or contact your admin to evaluate project proposals."
                    icon={
                        <LayoutGrid className="h-10 w-10 text-muted-foreground" />
                    }
                    ctaLabel="View plans"
                    ctaProps={{
                        onClick: () => navigate('/app/settings/billing'),
                    }}
                />
            </div>
        );
    }

    if (!rfpId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Proposal comparison</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Select an RFP"
                    description="Open the comparison screen from an RFP record to review proposals."
                    icon={
                        <FileSearch className="h-10 w-10 text-muted-foreground" />
                    }
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    if (rfpQuery.isLoading || proposalsQuery.isLoading) {
        return <RfpProposalReviewSkeleton />;
    }

    if (rfpQuery.isError || !rfpQuery.data) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Proposal comparison</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load RFP"
                    description={
                        rfpQuery.error?.message ??
                        'This project is unavailable or you lack access.'
                    }
                    icon={
                        <AlertTriangle className="h-10 w-10 text-destructive" />
                    }
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    if (proposalsQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Proposal comparison</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load proposals"
                    description={
                        proposalsQuery.error?.message ??
                        'Please retry or refresh the page.'
                    }
                    icon={
                        <AlertTriangle className="h-10 w-10 text-destructive" />
                    }
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => proposalsQuery.refetch() }}
                />
            </div>
        );
    }

    const rfp = rfpQuery.data;
    const proposalCount = summary?.total ?? proposals.length;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Compare proposals · {rfp.title}</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Project RFP
                    </p>
                    <h1 className="text-3xl font-semibold text-foreground">
                        {rfp.title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Evaluate{' '}
                        {proposalCount === 1
                            ? 'the latest proposal'
                            : `${proposalCount} proposals`}{' '}
                        for this project.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            rfp.status === 'published' ? 'default' : 'secondary'
                        }
                        className="h-9 rounded-full px-4 text-base capitalize"
                    >
                        {humanizeStatus(rfp.status)}
                    </Badge>
                    <Button type="button" variant="outline" asChild>
                        <Link to="/app/rfqs">Back to sourcing</Link>
                    </Button>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Buyer context</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <DetailPair
                            label="Problem & objectives"
                            value={rfp.problemObjectives}
                        />
                        <DetailPair label="Scope" value={rfp.scope} />
                        <DetailPair
                            label="Evaluation criteria"
                            value={rfp.evaluationCriteria}
                        />
                        <DetailPair
                            label="Proposal format"
                            value={rfp.proposalFormat}
                        />
                        <div className="grid gap-3 sm:grid-cols-2">
                            <DetailPair
                                label="Published"
                                value={formatDate(rfp.publishedAt)}
                            />
                            <DetailPair
                                label="Last updated"
                                value={formatDate(rfp.updatedAt)}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Highlights</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <HighlightItem
                            label="Total proposals"
                            value={proposalCount.toString()}
                        />
                        <HighlightItem
                            label="Best price"
                            value={
                                summary?.minPriceMinor == null ? '—' : undefined
                            }
                            money={summary?.minPriceMinor}
                            currency={
                                summary?.currency ??
                                proposals[0]?.currency ??
                                'USD'
                            }
                        />
                        <HighlightItem
                            label="Fastest lead time"
                            value={
                                summary?.minLeadTimeDays == null
                                    ? '—'
                                    : `${summary.minLeadTimeDays} days`
                            }
                        />
                        <HighlightItem
                            label="Slowest lead time"
                            value={
                                summary?.maxLeadTimeDays == null
                                    ? '—'
                                    : `${summary.maxLeadTimeDays} days`
                            }
                        />
                    </CardContent>
                </Card>
            </div>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Proposals</CardTitle>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    {proposals.length === 0 ? (
                        <div className="py-10">
                            <EmptyState
                                title="No proposals yet"
                                description="Invite suppliers or wait for submissions to compare side by side."
                                icon={
                                    <FileSearch className="h-8 w-8 text-muted-foreground" />
                                }
                            />
                        </div>
                    ) : (
                        <table className="min-w-full table-fixed border-collapse text-sm">
                            <thead>
                                <tr className="text-left text-xs tracking-wide text-muted-foreground uppercase">
                                    <th className="pr-4 pb-3 font-medium">
                                        Supplier
                                    </th>
                                    <th className="pr-4 pb-3 font-medium">
                                        Price
                                    </th>
                                    <th className="pr-4 pb-3 font-medium">
                                        Lead time
                                    </th>
                                    <th className="pr-4 pb-3 font-medium">
                                        Approach
                                    </th>
                                    <th className="pr-4 pb-3 font-medium">
                                        Attachments
                                    </th>
                                    <th className="pb-3 font-medium">
                                        Submitted
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {proposals.map((proposal) => (
                                    <tr
                                        key={proposal.id}
                                        className={cn(
                                            'border-t border-border/60 align-top transition hover:bg-muted/30',
                                            proposal.id === highlightPriceId &&
                                                'bg-emerald-50/60',
                                            proposal.id === highlightLeadId &&
                                                'ring-1 ring-blue-200',
                                        )}
                                    >
                                        <td className="py-3 pr-4 text-foreground">
                                            <p className="font-semibold">
                                                {proposal.supplierCompany
                                                    ?.name ?? 'Supplier'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                #
                                                {proposal.supplierCompanyId ??
                                                    '—'}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4">
                                            {proposal.priceTotalMinor ? (
                                                <MoneyCell
                                                    amountMinor={
                                                        proposal.priceTotalMinor
                                                    }
                                                    currency={
                                                        proposal.currency ??
                                                        'USD'
                                                    }
                                                    label="Total price"
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3 pr-4">
                                            {proposal.leadTimeDays ? (
                                                <span className="font-semibold">
                                                    {proposal.leadTimeDays} days
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3 pr-4 text-muted-foreground">
                                            <p className="line-clamp-3">
                                                {proposal.approachSummary ??
                                                    'No summary provided'}
                                            </p>
                                        </td>
                                        <td className="py-3 pr-4">
                                            <Badge variant="secondary">
                                                {proposal.attachmentsCount ?? 0}
                                            </Badge>
                                        </td>
                                        <td className="py-3 text-muted-foreground">
                                            {formatDate(proposal.createdAt)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function determineLowestPriceProposal(
    proposals: RfpProposalSummary[],
): number | null {
    let minValue = Number.POSITIVE_INFINITY;
    let proposalId: number | null = null;

    proposals.forEach((proposal) => {
        if (
            typeof proposal.priceTotalMinor === 'number' &&
            proposal.priceTotalMinor < minValue
        ) {
            minValue = proposal.priceTotalMinor;
            proposalId = proposal.id;
        }
    });

    return proposalId;
}

function determineFastestProposal(
    proposals: RfpProposalSummary[],
): number | null {
    let minValue = Number.POSITIVE_INFINITY;
    let proposalId: number | null = null;

    proposals.forEach((proposal) => {
        if (
            typeof proposal.leadTimeDays === 'number' &&
            proposal.leadTimeDays < minValue
        ) {
            minValue = proposal.leadTimeDays;
            proposalId = proposal.id;
        }
    });

    return proposalId;
}

function DetailPair({
    label,
    value,
}: {
    label: string;
    value?: string | null;
}) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm font-medium whitespace-pre-line text-foreground">
                {value && value.length ? value : '—'}
            </p>
        </div>
    );
}

function HighlightItem({
    label,
    value,
    money,
    currency,
}: {
    label: string;
    value?: string | null;
    money?: number | null;
    currency?: string | null;
}) {
    return (
        <div className="rounded-lg border border-border/60 p-4">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            {typeof money === 'number' ? (
                <MoneyCell
                    amountMinor={money}
                    currency={currency ?? 'USD'}
                    className="text-base font-semibold"
                />
            ) : (
                <p className="text-lg font-semibold text-foreground">
                    {value ?? '—'}
                </p>
            )}
        </div>
    );
}

function RfpProposalReviewSkeleton() {
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Proposal comparison</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />
            <Skeleton className="h-8 w-1/2" />
            <div className="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <Card className="border-border/70">
                    <CardContent className="space-y-4 p-6">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <div key={`detail-${index}`} className="space-y-2">
                                <Skeleton className="h-3 w-28" />
                                <Skeleton className="h-5 w-full" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card className="border-border/70">
                    <CardContent className="grid gap-4 p-6 md:grid-cols-2">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <Skeleton
                                key={`highlight-${index}`}
                                className="h-16 w-full"
                            />
                        ))}
                    </CardContent>
                </Card>
            </div>
            <Card className="border-border/70">
                <CardContent className="p-6">
                    <Skeleton className="h-6 w-1/3" />
                    <Skeleton className="mt-4 h-40 w-full" />
                </CardContent>
            </Card>
        </div>
    );
}

function humanizeStatus(status?: string | null) {
    if (!status) {
        return 'unknown';
    }

    return status.replace(/_/g, ' ');
}
