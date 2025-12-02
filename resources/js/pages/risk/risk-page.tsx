import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';
import { AlertTriangle, Leaf, RefreshCw, ShieldAlert, ShieldCheck } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useGenerateRiskScores, useRiskScores } from '@/hooks/api/risk/use-risk-scores';
import type { SupplierRiskScore } from '@/types/risk';
import { cn } from '@/lib/utils';

type GradeFilter = 'all' | 'low' | 'medium' | 'high';

const GRADE_FILTERS: Array<{ value: GradeFilter; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'low', label: 'Low risk' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

const GRADE_META: Record<Exclude<GradeFilter, 'all'>, { label: string; badgeClass: string; rowClass: string }> = {
    low: {
        label: 'Low',
        badgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        rowClass: 'border-l-2 border-l-emerald-400',
    },
    medium: {
        label: 'Medium',
        badgeClass: 'bg-amber-50 text-amber-800 border-amber-200',
        rowClass: 'border-l-2 border-l-amber-400',
    },
    high: {
        label: 'High',
        badgeClass: 'bg-rose-50 text-rose-800 border-rose-200',
        rowClass: 'border-l-2 border-l-rose-500 bg-rose-50/40 dark:bg-rose-500/5',
    },
};

const EMPTY_AGGREGATE = {
    scoredSuppliers: 0,
    gradeCounts: { low: 0, medium: 0, high: 0 },
    badgeSummary: [] as Array<{ label: string; count: number }>,
    averages: { overall: null, onTime: null, defect: null, responsiveness: null },
    latestPeriod: null as string | null,
    latestPeriodStart: null as string | null,
    latestPeriodEnd: null as string | null,
    lastUpdated: null as string | null,
};

export function RiskPage() {
    const { hasFeature, state } = useAuth();
    const { formatNumber, formatDate } = useFormatting();
    const [gradeFilter, setGradeFilter] = useState<GradeFilter>('all');
    const navigate = useNavigate();

    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const riskFeatureEnabled = hasFeature('risk.access') || hasFeature('risk_scores_enabled');
    const shouldLoadRisk = featureFlagsLoaded && riskFeatureEnabled;

    const filters = useMemo(() => (gradeFilter === 'all' ? {} : { grade: gradeFilter }), [gradeFilter]);
    const scoresQuery = useRiskScores(filters, { enabled: shouldLoadRisk });
    const generateMutation = useGenerateRiskScores();

    const scores = useMemo(() => scoresQuery.data?.scores ?? [], [scoresQuery.data?.scores]);
    const aggregate = useMemo(() => aggregateScores(scores), [scores]);

    if (featureFlagsLoaded && !riskFeatureEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Risk & ESG</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Risk scoring unavailable"
                    description="Upgrade your plan to unlock supplier risk scoring, ESG attestations, and corrective action tracking."
                    icon={<ShieldAlert className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View billing"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    const formatPercent = (value: number | null) =>
        value === null ? '—' : formatNumber(value, { style: 'percent', maximumFractionDigits: 0 });

    const stats = [
        {
            label: 'Suppliers scored',
            value: formatNumber(aggregate.scoredSuppliers, { maximumFractionDigits: 0 }),
            description: 'Vendors with telemetry in the current month.',
        },
        {
            label: 'High risk',
            value: formatNumber(aggregate.gradeCounts.high, { maximumFractionDigits: 0 }),
            description:
                aggregate.scoredSuppliers > 0
                    ? `${formatPercent(aggregate.gradeCounts.high / aggregate.scoredSuppliers)} of scored base`
                    : 'No suppliers flagged',
        },
        {
            label: 'Avg. on-time delivery',
            value: formatPercent(aggregate.averages.onTime),
            description: 'Reliability across all inbound receipts.',
        },
        {
            label: 'Avg. defect rate',
            value: formatPercent(aggregate.averages.defect),
            description: 'Rejected vs. received quantities.',
        },
    ];

    const latestSnapshotLabel = aggregate.latestPeriod
        ? `Period ${aggregate.latestPeriod}`
        : aggregate.lastUpdated
          ? `Updated ${formatDate(aggregate.lastUpdated, { dateStyle: 'medium' })}`
          : 'No snapshot yet';

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Risk & ESG</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-3">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Risk</p>
                    <h1 className="text-2xl font-semibold text-foreground">Risk cockpit</h1>
                    <p className="text-sm text-muted-foreground">
                        Blend delivery, quality, price volatility, and responsiveness signals to spotlight emerging vendor risk.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => scoresQuery.refetch()}
                        disabled={scoresQuery.isFetching}
                    >
                        <RefreshCw className={cn('mr-2 h-4 w-4', scoresQuery.isFetching && 'animate-spin')} /> Refresh
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={async () => {
                            try {
                                const refreshed = await generateMutation.mutateAsync(undefined);
                                publishToast({
                                    title: 'Risk snapshot regenerated',
                                    description: `Re-scored ${refreshed.length} suppliers for the selected period.`,
                                    variant: 'success',
                                });
                            } catch (error) {
                                void error;
                            }
                        }}
                        disabled={generateMutation.isPending}
                    >
                        <ShieldCheck className={cn('mr-2 h-4 w-4', generateMutation.isPending && 'animate-pulse')} />
                        {generateMutation.isPending ? 'Scoring…' : 'Generate snapshot'}
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => (
                    <Card key={stat.label} className="border-border/70">
                        <CardContent className="p-4">
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">{stat.label}</p>
                            {scoresQuery.isLoading ? (
                                <Skeleton className="mt-3 h-7 w-20" />
                            ) : (
                                <p className="mt-2 text-2xl font-semibold text-foreground">{stat.value}</p>
                            )}
                            <p className="text-xs text-muted-foreground">{stat.description}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-[2fr,1fr]">
                <Card className="border-border/70">
                    <CardHeader className="gap-2 pb-2">
                        <CardTitle className="text-base font-semibold">Latest snapshot</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Risk scoring combines delivery performance, defect trend, price/lead variance, and RFQ responsiveness per supplier.
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge variant="secondary" className="bg-muted text-foreground">
                                <ShieldAlert className="mr-1 h-3.5 w-3.5" /> {latestSnapshotLabel}
                            </Badge>
                            {aggregate.latestPeriodStart && aggregate.latestPeriodEnd && (
                                <span>
                                    Window {formatDate(aggregate.latestPeriodStart, { dateStyle: 'medium' })} –{' '}
                                    {formatDate(aggregate.latestPeriodEnd, { dateStyle: 'medium' })}
                                </span>
                            )}
                        </div>
                        <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
                            <span>
                                {aggregate.gradeCounts.high} High · {aggregate.gradeCounts.medium} Medium · {aggregate.gradeCounts.low} Low
                            </span>
                            <span>
                                Overall risk index:{' '}
                                {aggregate.averages.overall === null
                                    ? '—'
                                    : formatNumber(aggregate.averages.overall, { maximumFractionDigits: 2 })}
                            </span>
                        </div>
                        <div className="space-y-3">
                            <p className="text-sm font-medium text-foreground">Top drivers</p>
                            <div className="flex flex-wrap gap-2">
                                {aggregate.badgeSummary.length === 0 && !scoresQuery.isLoading ? (
                                    <Badge variant="outline" className="text-muted-foreground">
                                        <Leaf className="mr-1 h-3.5 w-3.5" /> Performance stable across suppliers
                                    </Badge>
                                ) : scoresQuery.isLoading ? (
                                    Array.from({ length: 3 }).map((_, index) => <Skeleton key={index} className="h-6 w-28" />)
                                ) : (
                                    aggregate.badgeSummary.slice(0, 5).map((signal) => (
                                        <Badge key={signal.label} variant="outline" className="gap-1 text-xs">
                                            <AlertTriangle className="h-3 w-3 text-amber-600" />
                                            {signal.label}
                                            <span className="text-muted-foreground">×{signal.count}</span>
                                        </Badge>
                                    ))
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader className="gap-2 pb-2">
                        <CardTitle className="text-base font-semibold">Filter by risk tier</CardTitle>
                        <p className="text-sm text-muted-foreground">Focus on specific grades to plan corrective actions.</p>
                    </CardHeader>
                    <CardContent>
                        <ToggleGroup
                            type="single"
                            value={gradeFilter}
                            onValueChange={(value) => value && setGradeFilter(value as GradeFilter)}
                            className="w-full"
                            variant="outline"
                        >
                            {GRADE_FILTERS.map((option) => (
                                <ToggleGroupItem key={option.value} value={option.value} className="flex-1 px-4 py-3 text-sm">
                                    {option.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Filtering happens server-side so telemetry stays scoped to the selected grade and tenant.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card className="border-border/70">
                <CardHeader className="flex flex-row items-start justify-between gap-4 pb-2">
                    <div>
                        <CardTitle className="text-base font-semibold">Supplier risk ledger</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Drill into each supplier’s score and the signals contributing to their badge.
                        </p>
                    </div>
                </CardHeader>
                <CardContent>
                    {scoresQuery.isLoading ? (
                        <RiskTableSkeleton />
                    ) : scoresQuery.isError ? (
                        <EmptyState
                            className="border-none bg-transparent"
                            title="Unable to load risk scores"
                            description="We could not retrieve supplier risk telemetry. Retry in a moment."
                            icon={<ShieldAlert className="h-10 w-10 text-muted-foreground" />}
                            ctaLabel="Retry"
                            ctaProps={{ onClick: () => scoresQuery.refetch() }}
                        />
                    ) : scores.length === 0 ? (
                        <EmptyState
                            className="border-none bg-transparent"
                            title="No telemetry yet"
                            description="Once purchase orders, receipts, and RFQs accumulate, supplier risk scores will populate here."
                            icon={<Leaf className="h-10 w-10 text-muted-foreground" />}
                            ctaLabel="Invite suppliers"
                            ctaProps={{ onClick: () => navigate('/app/suppliers/new') }}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-border/70 text-sm">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <th className="py-2 pr-4">Supplier</th>
                                        <th className="py-2 pr-4">Grade</th>
                                        <th className="py-2 pr-4">Risk score</th>
                                        <th className="py-2 pr-4">On-time</th>
                                        <th className="py-2 pr-4">Defect</th>
                                        <th className="py-2 pr-4">Responsiveness</th>
                                        <th className="py-2">Signals</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {scores.map((score) => (
                                        <RiskTableRow key={score.supplierId} score={score} formatPercent={formatPercent} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function RiskTableRow({
    score,
    formatPercent,
}: {
    score: SupplierRiskScore;
    formatPercent: (value: number | null) => string;
}) {
    const meta = score.riskGrade ? GRADE_META[score.riskGrade] : null;
    const gradeBadge = score.riskGrade && meta ? (
        <Badge variant="outline" className={cn('text-xs capitalize', meta.badgeClass)}>
            {meta.label}
        </Badge>
    ) : (
        <Badge variant="outline" className="text-xs text-muted-foreground">
            Unscored
        </Badge>
    );

    const supplierLabel = score.supplierName ?? `Supplier #${score.supplierId}`;

    return (
        <tr className={cn('align-top transition hover:bg-muted/40', meta?.rowClass)}>
            <td className="py-4 pr-4">
                <div className="flex flex-col">
                    <Link to={`/app/suppliers/${score.supplierId}`} className="font-medium text-primary">
                        {supplierLabel}
                    </Link>
                    <p className="text-xs text-muted-foreground">ID {score.supplierId}</p>
                </div>
            </td>
            <td className="py-4 pr-4">{gradeBadge}</td>
            <td className="py-4 pr-4">
                <ScoreBar value={score.overallScore} />
            </td>
            <td className="py-4 pr-4">{formatPercent(score.onTimeDeliveryRate)}</td>
            <td className="py-4 pr-4">{formatPercent(score.defectRate)}</td>
            <td className="py-4 pr-4">{formatPercent(score.responsivenessRate)}</td>
            <td className="py-4">
                <div className="flex flex-wrap gap-1">
                    {score.badges.slice(0, 3).map((badge) => (
                        <Badge key={badge} variant="outline" className="text-[11px]">
                            {badge}
                        </Badge>
                    ))}
                    {score.badges.length === 0 && <span className="text-xs text-muted-foreground">Stable performance</span>}
                </div>
            </td>
        </tr>
    );
}

function ScoreBar({ value }: { value: number | null }) {
    if (value === null) {
        return <span className="text-xs text-muted-foreground">No data</span>;
    }

    const percent = Math.round(Math.max(0, Math.min(1, value)) * 100);

    return (
        <div className="min-w-[150px]">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>Index</span>
                <span>{percent}%</span>
            </div>
            <div className="mt-1 h-2 w-full rounded-full bg-muted">
                <div className="h-2 rounded-full bg-primary" style={{ width: `${percent}%` }} />
            </div>
        </div>
    );
}

function RiskTableSkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="grid grid-cols-7 gap-4">
                    <Skeleton className="h-6" />
                    <Skeleton className="h-6" />
                    <Skeleton className="h-6" />
                    <Skeleton className="h-6" />
                    <Skeleton className="h-6" />
                    <Skeleton className="h-6" />
                    <Skeleton className="h-6" />
                </div>
            ))}
        </div>
    );
}

function aggregateScores(scores: SupplierRiskScore[]) {
    if (scores.length === 0) {
        return EMPTY_AGGREGATE;
    }

    const gradeCounts = { low: 0, medium: 0, high: 0 } satisfies Record<'low' | 'medium' | 'high', number>;
    const badgeCounter = new Map<string, number>();

    scores.forEach((score) => {
        if (score.riskGrade && gradeCounts[score.riskGrade] !== undefined) {
            gradeCounts[score.riskGrade] += 1;
        }
        score.badges.forEach((badge) => {
            if (!badge) {
                return;
            }
            badgeCounter.set(badge, (badgeCounter.get(badge) ?? 0) + 1);
        });
    });

    const averages = {
        overall: computeAverage(scores.map((score) => score.overallScore)),
        onTime: computeAverage(scores.map((score) => score.onTimeDeliveryRate)),
        defect: computeAverage(scores.map((score) => score.defectRate)),
        responsiveness: computeAverage(scores.map((score) => score.responsivenessRate)),
    };

    const top = scores[0];

    return {
        scoredSuppliers: scores.length,
        gradeCounts,
        badgeSummary: Array.from(badgeCounter.entries())
            .sort((a, b) => b[1] - a[1])
            .map(([label, count]) => ({ label, count })),
        averages,
        latestPeriod: typeof top?.meta?.periodKey === 'string' ? top.meta.periodKey : null,
        latestPeriodStart: typeof top?.meta?.periodStart === 'string' ? top.meta.periodStart : null,
        latestPeriodEnd: typeof top?.meta?.periodEnd === 'string' ? top.meta.periodEnd : null,
        lastUpdated: top?.updatedAt ?? null,
    };
}

function computeAverage(values: Array<number | null | undefined>): number | null {
    const filtered = values.filter((value): value is number => typeof value === 'number' && Number.isFinite(value));
    if (filtered.length === 0) {
        return null;
    }
    const total = filtered.reduce((sum, value) => sum + value, 0);
    return total / filtered.length;
}
