import {
    AlertTriangle,
    BarChart3,
    ClipboardList,
    HelpCircle,
    LineChart,
    RefreshCw,
    Sparkles,
} from 'lucide-react';
import { Link } from 'react-router-dom';

import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFormatting } from '@/contexts/formatting-context';
import { useAdminAiUsageMetrics } from '@/hooks/api/admin/use-admin-ai-usage-metrics';
import type { AiAdminUsageMetrics } from '@/types/admin';

type FormatNumberFn = ReturnType<typeof useFormatting>['formatNumber'];

export function AdminAiUsageDashboardPage() {
    const {
        data: metrics,
        isLoading,
        isError,
        error,
        refetch,
        isRefetching,
    } = useAdminAiUsageMetrics();
    const { formatNumber, formatDate } = useFormatting();

    const windowLabel = metrics
        ? `${formatDate(metrics.window_start, { dateStyle: 'medium' })} - ${formatDate(metrics.window_end, { dateStyle: 'medium' })}`
        : null;

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <Heading
                        title="AI usage dashboard"
                        description="Track Copilot activity, adoption, and failure modes across the tenant footprint."
                    />
                    {metrics ? (
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge variant="outline">
                                Last {metrics.window_days}-day window
                            </Badge>
                            {windowLabel ? <span>{windowLabel}</span> : null}
                        </div>
                    ) : null}
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => refetch()}
                        disabled={isRefetching}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" aria-hidden />
                        Refresh
                    </Button>
                    <Button asChild variant="secondary" size="sm">
                        <Link to="/app/admin/ai-events">
                            Open AI activity log
                        </Link>
                    </Button>
                </div>
            </div>

            {isError ? (
                <Alert variant="destructive">
                    <AlertTriangle className="h-5 w-5" aria-hidden />
                    <AlertTitle>Unable to load usage metrics</AlertTitle>
                    <AlertDescription>
                        {error instanceof Error
                            ? error.message
                            : 'Unexpected error encountered.'}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-2">
                <MetricCard
                    title="Copilot actions"
                    description="Drafts submitted vs. approvals completed"
                    icon={ClipboardList}
                    isLoading={isLoading}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <MetricStat
                            label="Drafts planned"
                            value={formatNumber(
                                metrics?.actions.planned ?? null,
                                { maximumFractionDigits: 0 },
                            )}
                        />
                        <MetricStat
                            label="Approvals completed"
                            value={formatNumber(
                                metrics?.actions.approved ?? null,
                                { maximumFractionDigits: 0 },
                            )}
                            intent="success"
                        />
                        <MetricStat
                            label="Approval rate"
                            value={formatApprovalRate(metrics)}
                            intent="info"
                        />
                    </div>
                </MetricCard>
                <MetricCard
                    title="Forecast jobs"
                    description="Model output vs. failures"
                    icon={LineChart}
                    isLoading={isLoading}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <MetricStat
                            label="Forecasts generated"
                            value={formatNumber(
                                metrics?.forecasts.generated ?? null,
                                { maximumFractionDigits: 0 },
                            )}
                            intent="success"
                        />
                        <MetricStat
                            label="Errors reported"
                            value={formatNumber(
                                metrics?.forecasts.errors ?? null,
                                { maximumFractionDigits: 0 },
                            )}
                            intent={
                                metrics && metrics.forecasts.errors > 0
                                    ? 'danger'
                                    : 'info'
                            }
                        />
                    </div>
                </MetricCard>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <MetricCard
                    title="Help requests"
                    description="Workspace help tool invocations"
                    icon={HelpCircle}
                    isLoading={isLoading}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <MetricStat
                            label="Total requests"
                            value={formatNumber(
                                metrics?.help_requests.total ?? null,
                                { maximumFractionDigits: 0 },
                            )}
                        />
                        <MetricStat
                            label="Avg per day"
                            value={formatAveragePerDay(
                                metrics?.help_requests.total ?? null,
                                metrics?.window_days,
                            )}
                            intent="info"
                        />
                    </div>
                </MetricCard>
                <Card className="h-full">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <BarChart3
                                className="h-5 w-5 text-primary"
                                aria-hidden
                            />
                            Tool failures
                        </CardTitle>
                        <CardDescription>
                            Top error sources inside the window.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <Skeleton className="h-32 w-full" />
                        ) : metrics && metrics.tool_errors.total > 0 ? (
                            <ToolErrorTable
                                metrics={metrics}
                                formatNumber={formatNumber}
                            />
                        ) : (
                            <EmptyState
                                icon={
                                    <Sparkles className="h-6 w-6" aria-hidden />
                                }
                                title="No errors detected"
                                description="Copilot tools have not produced recent failures."
                                className="py-8"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Deep dive</CardTitle>
                    <CardDescription>
                        Jump into the AI activity log for per-event payloads,
                        latency, and call stacks.
                    </CardDescription>
                </CardHeader>
                <CardFooter className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="text-sm text-muted-foreground">
                        Filter the log by feature or status to investigate
                        outliers surfaced on this dashboard.
                    </div>
                    <Button asChild variant="outline">
                        <Link to="/app/admin/ai-events">
                            View AI activity log
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}

function MetricCard({
    title,
    description,
    icon: Icon,
    isLoading,
    children,
}: {
    title: string;
    description: string;
    icon: typeof ClipboardList;
    isLoading: boolean;
    children: React.ReactNode;
}) {
    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-center gap-3">
                <div className="rounded-full bg-primary/10 p-2 text-primary">
                    <Icon className="h-5 w-5" aria-hidden />
                </div>
                <div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
            </CardHeader>
            <CardContent>
                {isLoading ? <Skeleton className="h-32 w-full" /> : children}
            </CardContent>
        </Card>
    );
}

function MetricStat({
    label,
    value,
    intent,
}: {
    label: string;
    value: string;
    intent?: 'success' | 'danger' | 'info';
}) {
    const intentClass =
        intent === 'success'
            ? 'text-emerald-600'
            : intent === 'danger'
              ? 'text-rose-600'
              : intent === 'info'
                ? 'text-primary'
                : 'text-foreground';

    return (
        <div className="rounded-lg border bg-muted/20 p-4">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className={`text-2xl font-semibold ${intentClass}`}>{value}</p>
        </div>
    );
}

function ToolErrorTable({
    metrics,
    formatNumber,
}: {
    metrics: AiAdminUsageMetrics;
    formatNumber: FormatNumberFn;
}) {
    return (
        <div className="space-y-4">
            <div className="text-sm text-muted-foreground">
                {formatNumber(metrics.tool_errors.total ?? null, {
                    maximumFractionDigits: 0,
                })}{' '}
                failures recorded
            </div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Feature</TableHead>
                        <TableHead className="text-right">Errors</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {metrics.tool_errors.by_feature.map((entry) => {
                        const label = entry.feature || 'unknown';
                        return (
                            <TableRow key={label}>
                                <TableCell className="font-medium capitalize">
                                    {label}
                                </TableCell>
                                <TableCell className="text-right">
                                    {formatNumber(entry.count, {
                                        maximumFractionDigits: 0,
                                    })}
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

function formatApprovalRate(metrics?: AiAdminUsageMetrics): string {
    if (!metrics || metrics.actions.approval_rate === null) {
        return 'N/A';
    }

    return `${metrics.actions.approval_rate.toFixed(1)}%`;
}

function formatAveragePerDay(
    total: number | null,
    windowDays?: number,
): string {
    if (!total || !windowDays || windowDays <= 0) {
        return '0';
    }

    const avg = total / windowDays;
    return avg < 1 ? avg.toFixed(2) : avg.toFixed(1);
}
