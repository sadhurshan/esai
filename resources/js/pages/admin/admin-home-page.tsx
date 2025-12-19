import type { ComponentType, ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { Activity, BadgeInfo, Building2, Database, KeyRound, Layers2, LineChart, ListChecks, RadioTower, ScrollText, ShieldCheck, Sparkles, Users } from 'lucide-react';

import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/empty-state';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useAdminAnalyticsOverview } from '@/hooks/api/admin/use-admin-analytics-overview';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { AdminAnalyticsOverview, AdminAnalyticsRecentCompany, AdminAnalyticsTrendPoint } from '@/types/admin';
import { cn } from '@/lib/utils';

const quickLinks = [
    {
        title: 'Company approvals',
        description: 'Review pending tenants and approve or reject onboarding.',
        href: '/app/admin/company-approvals',
        icon: Building2,
    },
    {
        title: 'Supplier applications',
        description: 'Verify KYC documents and activate supplier access.',
        href: '/app/admin/supplier-applications',
        icon: Users,
    },
    {
        title: 'Plans & features',
        description: 'Control plan pricing, seat limits, and feature toggles.',
        href: '/app/admin/plans',
        icon: Layers2,
    },
    {
        title: 'Roles & permissions',
        description: 'Define workspace roles and granular access scopes.',
        href: '/app/admin/roles',
        icon: ListChecks,
    },
    {
        title: 'API keys',
        description: 'Provision credentials for integrations and partners.',
        href: '/app/admin/api-keys',
        icon: KeyRound,
    },
    {
        title: 'Webhooks',
        description: 'Manage outbound callbacks and delivery retries.',
        href: '/app/admin/webhooks',
        icon: RadioTower,
    },
    {
        title: 'Rate limits',
        description: 'Enforce per-scope throttles across public APIs.',
        href: '/app/admin/rate-limits',
        icon: Activity,
    },
    {
        title: 'Audit log',
        description: 'Review privileged actions and export compliance trails.',
        href: '/app/admin/audit',
        icon: ScrollText,
    },
    {
        title: 'AI activity log',
        description: 'Debug AI usage, latency, and errors across every tenant.',
        href: '/app/admin/ai-events',
        icon: Sparkles,
    },
    {
        title: 'AI model health',
        description: 'Review MAPE, MAE, and calibration drift signals.',
        href: '/app/admin/ai-model-health',
        icon: LineChart,
    },
];

export function AdminHomePage() {
    const { isAdmin, canAccessAdminConsole } = useAuth();
    const { data: analytics, isLoading, isError, error, refetch, isRefetching } = useAdminAnalyticsOverview();
    const { formatNumber, formatDate } = useFormatting();

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="Admin console"
                    description="Monitor multi-tenant health, usage, and privileged activity in one view."
                />
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline" className="w-fit uppercase tracking-wide">
                        Admin only
                    </Badge>
                    <Button type="button" variant="outline" size="sm" onClick={() => refetch()} disabled={isRefetching}>
                        Refresh
                    </Button>
                </div>
            </div>

            {!canAccessAdminConsole ? (
                <Alert variant="destructive" className="max-w-3xl">
                    <BadgeInfo className="h-5 w-5" aria-hidden />
                    <AlertTitle>Admin console disabled for this tenant</AlertTitle>
                    <AlertDescription>
                        Your current plan does not include the admin console entitlement. Enable the `admin_console_enabled` feature
                        flag in billing to expose these controls to tenant admins.
                    </AlertDescription>
                </Alert>
            ) : null}

            {isError ? (
                <Alert variant="destructive" className="max-w-3xl">
                    <BadgeInfo className="h-5 w-5" aria-hidden />
                    <AlertTitle>Unable to load analytics</AlertTitle>
                    <AlertDescription>{error instanceof Error ? error.message : 'Unexpected error'}</AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-4 xl:grid-cols-3">
                <StatCard
                    title="Tenant health"
                    description="Status mix across every company"
                    icon={Building2}
                    isLoading={isLoading}
                >
                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Stat value={analytics?.tenants.total} label="Total tenants" formatNumber={formatNumber} />
                        <Stat value={analytics?.tenants.active} label="Active" formatNumber={formatNumber} intent="success" />
                        <Stat value={analytics?.tenants.trialing} label="Trialing" formatNumber={formatNumber} />
                        <Stat value={analytics?.tenants.pending} label="Pending approval" formatNumber={formatNumber} intent="warning" />
                        <Stat value={analytics?.tenants.suspended} label="Suspended" formatNumber={formatNumber} intent="danger" />
                    </dl>
                </StatCard>
                <StatCard title="Usage throughput" description="Month-to-date velocity" icon={Activity} isLoading={isLoading}>
                    <dl className="grid gap-4">
                        <PrimaryMetric
                            label="RFQs this month"
                            current={analytics?.usage.rfqs_month_to_date}
                            previous={analytics?.usage.rfqs_last_month}
                            formatNumber={formatNumber}
                        />
                        <Stat value={analytics?.usage.quotes_month_to_date} label="Quotes submitted" formatNumber={formatNumber} />
                        <Stat value={analytics?.usage.purchase_orders_month_to_date} label="POs created" formatNumber={formatNumber} />
                        <Stat
                            value={convertMbToGb(analytics?.usage.storage_used_mb)}
                            label="Storage consumed (GB)"
                            formatNumber={formatNumber}
                        />
                        <Stat
                            value={convertMbToGb(analytics?.usage.avg_storage_used_mb)}
                            label="Avg storage per tenant (GB)"
                            formatNumber={formatNumber}
                        />
                    </dl>
                </StatCard>
                <StatCard title="People & approvals" description="Engagement + backlog" icon={Users} isLoading={isLoading}>
                    <dl className="grid gap-4">
                        <Stat value={analytics?.people.users_total} label="Total users" formatNumber={formatNumber} />
                        <Stat value={analytics?.people.active_last_7_days} label="Active last 7 days" formatNumber={formatNumber} intent="success" />
                        <Stat value={analytics?.people.listed_suppliers} label="Listed suppliers" formatNumber={formatNumber} />
                        <Stat value={analytics?.approvals.pending_companies} label="Pending companies" formatNumber={formatNumber} intent="warning" />
                        <Stat
                            value={analytics?.approvals.pending_supplier_applications}
                            label="Supplier applications"
                            formatNumber={formatNumber}
                            intent="warning"
                        />
                    </dl>
                </StatCard>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <TrendCard
                    title="RFQ momentum"
                    description="Rolling six-month submission cadence"
                    icon={LineChart}
                    points={analytics?.trends.rfqs ?? []}
                    formatNumber={formatNumber}
                    formatDate={formatDate}
                    isLoading={isLoading}
                />
                <TrendCard
                    title="Tenant growth"
                    description="Monthly company creation trend"
                    icon={LineChart}
                    points={analytics?.trends.tenants ?? []}
                    formatNumber={formatNumber}
                    formatDate={formatDate}
                    isLoading={isLoading}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <RecentCompaniesTable
                    companies={analytics?.recent.companies ?? []}
                    isLoading={isLoading}
                    formatNumber={formatNumber}
                    formatDate={formatDate}
                />
                <RecentAuditList entries={analytics?.recent.audit_logs ?? []} isLoading={isLoading} formatDate={formatDate} />
            </div>

            <section className="space-y-4">
                <div className="flex items-center gap-3">
                    <ShieldCheck className="h-5 w-5 text-muted-foreground" aria-hidden />
                    <div>
                        <h3 className="text-base font-semibold">Admin areas</h3>
                        <p className="text-sm text-muted-foreground">Navigate to each administration module.</p>
                    </div>
                </div>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {quickLinks.map(({ title, description, href, icon: Icon }) => (
                        <Link key={href} to={href} className="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                            <Card className="h-full transition hover:border-primary/50">
                                <CardHeader className="flex flex-row items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>{title}</CardTitle>
                                        <CardDescription>{description}</CardDescription>
                                    </div>
                                    <div className="rounded-full bg-primary/10 p-2 text-primary">
                                        <Icon className="h-5 w-5" aria-hidden />
                                    </div>
                                </CardHeader>
                                <CardFooter className="text-sm text-primary">Open module →</CardFooter>
                            </Card>
                        </Link>
                    ))}
                </div>
            </section>
        </div>
    );
}

interface StatCardProps {
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
    isLoading: boolean;
    children: ReactNode;
}

function StatCard({ title, description, icon: Icon, isLoading, children }: StatCardProps) {
    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <Icon className="h-5 w-5" aria-hidden />
                </div>
            </CardHeader>
            <CardContent>{isLoading ? <Skeleton className="h-32 w-full" /> : children}</CardContent>
        </Card>
    );
}

function Stat({
    value,
    label,
    intent,
    formatNumber,
}: {
    value: number | null | undefined;
    label: string;
    intent?: 'success' | 'warning' | 'danger';
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
}) {
    const display = formatNumber(value ?? null, { maximumFractionDigits: 0 });
    const accent =
        intent === 'success'
            ? 'text-emerald-600'
            : intent === 'warning'
              ? 'text-amber-600'
              : intent === 'danger'
                ? 'text-rose-600'
                : 'text-foreground';

    return (
        <div className="rounded-lg border bg-muted/30 p-4">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className={cn('text-2xl font-semibold', accent)}>{display}</p>
        </div>
    );
}

function PrimaryMetric({
    label,
    current,
    previous,
    formatNumber,
}: {
    label: string;
    current: number | null | undefined;
    previous: number | null | undefined;
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
}) {
    const currentLabel = formatNumber(current ?? null, { maximumFractionDigits: 0 });
    const deltaLabel = buildDeltaLabel(current, previous, formatNumber);

    return (
        <div className="rounded-lg border bg-background/80 p-4 shadow-sm">
            <p className="text-sm font-medium text-muted-foreground">{label}</p>
            <p className="mt-1 text-3xl font-semibold text-foreground">{currentLabel}</p>
            <p className={cn('text-sm', deltaLabel?.direction === 'up' ? 'text-emerald-600' : deltaLabel?.direction === 'down' ? 'text-rose-600' : 'text-muted-foreground')}>
                {deltaLabel?.text ?? 'Awaiting prior data'}
            </p>
        </div>
    );
}

function buildDeltaLabel(
    current: number | null | undefined,
    previous: number | null | undefined,
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'],
) {
    if (current === undefined || current === null || previous === undefined || previous === null) {
        return null;
    }

    const diff = current - previous;
    if (diff === 0) {
        return { text: 'No change vs last month', direction: 'flat' as const };
    }

    const direction = diff > 0 ? ('up' as const) : ('down' as const);
    const formatted = formatNumber(Math.abs(diff), { maximumFractionDigits: 0 });
    return {
        text: `${diff > 0 ? '+' : '-'}${formatted} vs last month`,
        direction,
    };
}

function TrendCard({
    title,
    description,
    icon: Icon,
    points,
    formatNumber,
    formatDate,
    isLoading,
}: {
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
    points: AdminAnalyticsTrendPoint[];
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
    formatDate: ReturnType<typeof useFormatting>['formatDate'];
    isLoading: boolean;
}) {
    const recentPoints = points.slice(-6);
    const max = Math.max(...recentPoints.map((point) => point.count), 1);

    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <Icon className="h-5 w-5" aria-hidden />
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-40 w-full" />
                ) : recentPoints.length ? (
                    <div className="space-y-3">
                        {recentPoints.map((point) => (
                            <div key={point.period} className="flex items-center gap-3">
                                <span className="w-20 text-xs font-medium text-muted-foreground">
                                    {formatPeriodLabel(point.period, formatDate)}
                                </span>
                                <div className="h-2 flex-1 rounded-full bg-muted/40">
                                    <div
                                        className="h-2 rounded-full bg-primary"
                                        style={{ width: `${(point.count / max) * 100}%` }}
                                    />
                                </div>
                                <span className="w-12 text-right text-sm font-semibold text-foreground">
                                    {formatNumber(point.count, { maximumFractionDigits: 0 })}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={<LineChart className="h-8 w-8" aria-hidden />}
                        title="No historical data"
                        description="Usage snapshots will populate after the first nightly job runs."
                    />
                )}
            </CardContent>
        </Card>
    );
}

function formatPeriodLabel(period: string, formatDate: ReturnType<typeof useFormatting>['formatDate']) {
    const safe = `${period}-01T00:00:00Z`;
    return formatDate(safe, { month: 'short', year: 'numeric' });
}

function RecentCompaniesTable({
    companies,
    isLoading,
    formatNumber,
    formatDate,
}: {
    companies: AdminAnalyticsRecentCompany[];
    isLoading: boolean;
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
    formatDate: ReturnType<typeof useFormatting>['formatDate'];
}) {
    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle>Newest tenants</CardTitle>
                    <CardDescription>Last five companies created across the network.</CardDescription>
                </div>
                <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <Database className="h-5 w-5" aria-hidden />
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-56 w-full" />
                ) : companies.length ? (
                    <div className="space-y-3">
                        {companies.map((company) => (
                            <div key={company.id} className="rounded-lg border bg-muted/20 p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-foreground">{company.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Joined {formatDate(company.created_at ?? null, { dateStyle: 'medium' })}
                                        </p>
                                    </div>
                                    <StatusBadge status={company.status ?? 'unknown'} />
                                </div>
                                <dl className="mt-3 grid gap-3 text-sm text-muted-foreground sm:grid-cols-3">
                                    <div>
                                        <dt className="text-xs uppercase tracking-wide">Plan</dt>
                                        <dd>{company.plan?.name ?? 'Unassigned'}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs uppercase tracking-wide">RFQs used</dt>
                                        <dd>{formatNumber(company.rfqs_monthly_used ?? null, { maximumFractionDigits: 0 })}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs uppercase tracking-wide">Storage (MB)</dt>
                                        <dd>{formatNumber(company.storage_used_mb ?? null, { maximumFractionDigits: 0 })}</dd>
                                    </div>
                                </dl>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={<Building2 className="h-8 w-8" aria-hidden />}
                        title="No recent tenants"
                        description="Approve or onboard a new company to populate this feed."
                    />
                )}
            </CardContent>
        </Card>
    );
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status?.toLowerCase?.() ?? 'unknown';
    const variant =
        normalized === 'active'
            ? 'bg-emerald-100 text-emerald-900'
            : normalized === 'pending' || normalized === 'pending_verification'
              ? 'bg-amber-100 text-amber-900'
              : normalized === 'suspended'
                ? 'bg-rose-100 text-rose-900'
                : 'bg-muted text-foreground';

    return <Badge className={cn('uppercase', variant)}>{status}</Badge>;
}

function RecentAuditList({
    entries,
    isLoading,
    formatDate,
}: {
    entries: AdminAnalyticsOverview['recent']['audit_logs'];
    isLoading: boolean;
    formatDate: ReturnType<typeof useFormatting>['formatDate'];
}) {
    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle>Latest audit events</CardTitle>
                    <CardDescription>Privileged actions captured in the last 24 hours.</CardDescription>
                </div>
                <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <ScrollText className="h-5 w-5" aria-hidden />
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <Skeleton className="h-56 w-full" />
                ) : entries.length ? (
                    <div className="space-y-3">
                        {entries.map((entry) => (
                            <div key={entry.id} className="rounded-lg border bg-muted/20 p-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <Badge variant="outline" className="font-mono text-[11px] uppercase">
                                        {entry.event}
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {formatDate(entry.timestamp ?? null, { dateStyle: 'medium', timeStyle: 'short' })}
                                    </span>
                                </div>
                                <p className="mt-2 text-sm font-medium text-foreground">{entry.actor?.name ?? 'System'}</p>
                                <p className="text-xs text-muted-foreground">{entry.resource?.label ?? entry.resource?.type ?? 'Resource'}</p>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={<ScrollText className="h-8 w-8" aria-hidden />}
                        title="No recent audits"
                        description="Security and billing actions will populate this feed."
                    />
                )}
            </CardContent>
        </Card>
    );
}

function convertMbToGb(value?: number | null) {
    if (value === null || value === undefined) {
        return null;
    }

    return value / 1024;
}
