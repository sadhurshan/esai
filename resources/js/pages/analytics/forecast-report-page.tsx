import {
    AlertTriangle,
    CalendarRange,
    ChevronDown,
    Filter,
    Loader2,
    RefreshCcw,
    ShieldAlert,
    Sparkles,
    TrendingUp,
    X,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type KeyboardEvent,
} from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';

import { ForecastLineChart } from '@/components/analytics/forecast-line-chart';
import { MetricsTable } from '@/components/analytics/metrics-table';
import { MiniChart } from '@/components/analytics/mini-chart';
import type { DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import {
    useForecastReport,
    type ForecastReportParams,
    type ForecastReportRow,
} from '@/hooks/api/analytics/use-analytics';
import { useItems } from '@/hooks/api/inventory/use-items';
import { useLocations } from '@/hooks/api/inventory/use-locations';
import { isForbiddenError, type ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';

interface FilterFormState {
    startDate: string;
    endDate: string;
    partIds: string[];
    categoryIds: string[];
    locationIds: string[];
}

interface FilterMultiSelectOption {
    value: string;
    label: string;
    description?: string;
}

interface FilterSummaryChip {
    key: string;
    label: string;
}

interface EnrichedForecastRow extends ForecastReportRow {
    variance: number;
}

const FORECAST_SORTABLE_KEYS = [
    'totalActual',
    'totalForecast',
    'variance',
    'mape',
    'mae',
    'reorderPoint',
    'safetyStock',
] as const;

type ForecastSortableKey = (typeof FORECAST_SORTABLE_KEYS)[number];

const BUCKET_LABELS: Record<string, string> = {
    daily: 'Daily buckets',
    weekly: 'Weekly buckets',
    monthly: 'Monthly buckets',
};

const DEFAULT_FILTER_BUCKET = 'daily';
const FILTER_PICKER_PAGE_SIZE = 100;

export function ForecastReportPage() {
    const { hasFeature, notifyPlanLimit, clearPlanLimit } = useAuth();
    const navigate = useNavigate();
    const { formatNumber, formatDate } = useFormatting();
    const analyticsEnabled = hasFeature('analytics.access');

    const [filterDraft, setFilterDraft] = useState<FilterFormState>(() =>
        createDefaultFilters(),
    );
    const [appliedFilters, setAppliedFilters] = useState<FilterFormState>(() =>
        createDefaultFilters(),
    );
    const [tableSort, setTableSort] = useState<{
        key: ForecastSortableKey;
        direction: 'asc' | 'desc';
    }>({
        key: 'totalActual',
        direction: 'desc',
    });

    useEffect(() => {
        if (!analyticsEnabled) {
            notifyPlanLimit({
                featureKey: 'analytics.access',
                message:
                    'Upgrade your plan to unlock the inventory forecast workspace.',
            });

            return () => {
                clearPlanLimit();
            };
        }

        return undefined;
    }, [analyticsEnabled, clearPlanLimit, notifyPlanLimit]);

    const forecastParams = useMemo(
        () => mapFiltersToParams(appliedFilters),
        [appliedFilters],
    );
    const forecastQuery = useForecastReport(forecastParams, {
        enabled: analyticsEnabled,
    });
    const planForbidden =
        forecastQuery.isError &&
        isForbiddenError(forecastQuery.error, 'analytics_upgrade_required');
    const permissionDenied =
        forecastQuery.isError &&
        isForbiddenError(forecastQuery.error, 'forecast_permission_required');

    const itemsQuery = useItems({
        perPage: FILTER_PICKER_PAGE_SIZE,
        status: 'active',
        enabled: analyticsEnabled,
    });
    const locationsQuery = useLocations({
        perPage: FILTER_PICKER_PAGE_SIZE,
        enabled: analyticsEnabled,
    });

    const partOptions = useMemo<FilterMultiSelectOption[]>(() => {
        return (itemsQuery.data?.items ?? []).map((item) => ({
            value: item.id,
            label: `${item.sku} • ${item.name}`,
            description: item.category ?? undefined,
        }));
    }, [itemsQuery.data?.items]);

    const locationOptions = useMemo<FilterMultiSelectOption[]>(() => {
        return (locationsQuery.data?.items ?? []).map((location) => ({
            value: location.id,
            label: location.name,
            description: location.siteName ?? undefined,
        }));
    }, [locationsQuery.data?.items]);

    const partLabelLookup = useMemo(() => {
        const map = new Map<number, string>();
        (itemsQuery.data?.items ?? []).forEach((item) => {
            const numericId = Number(item.id);
            if (Number.isFinite(numericId)) {
                map.set(numericId, `${item.sku} • ${item.name}`.trim());
            }
        });
        return map;
    }, [itemsQuery.data?.items]);

    const locationLabelLookup = useMemo(() => {
        const map = new Map<number, string>();
        (locationsQuery.data?.items ?? []).forEach((location) => {
            const numericId = Number(location.id);
            if (Number.isFinite(numericId)) {
                map.set(numericId, location.name);
            }
        });
        return map;
    }, [locationsQuery.data?.items]);

    const categorySuggestions = useMemo(() => {
        const set = new Set<string>();
        (itemsQuery.data?.items ?? []).forEach((item) => {
            if (item.category) {
                set.add(item.category);
            }
        });
        return Array.from(set).sort((a, b) => a.localeCompare(b));
    }, [itemsQuery.data?.items]);

    const report = forecastQuery.data?.report;
    const summary = forecastQuery.data?.summary;

    const varianceData = useMemo(
        () => buildVarianceComparison(report?.table ?? []),
        [report?.table],
    );

    const tableRows = useMemo<EnrichedForecastRow[]>(() => {
        if (!report?.table) {
            return [];
        }

        return [...report.table]
            .map((row) => ({
                ...row,
                variance: row.totalActual - row.totalForecast,
            }))
            .sort((a, b) =>
                sortByMetric(a, b, tableSort.key, tableSort.direction),
            );
    }, [report, tableSort]);

    const filterChips = useMemo<FilterSummaryChip[]>(() => {
        return buildFilterChips(
            appliedFilters,
            partLabelLookup,
            locationLabelLookup,
        );
    }, [appliedFilters, partLabelLookup, locationLabelLookup]);

    const aggregateStats = useMemo(
        () => [
            {
                key: 'totalActual',
                label: 'Observed demand',
                value: formatNumber(report?.aggregates.totalActual ?? 0, {
                    maximumFractionDigits: 0,
                    fallback: '—',
                }),
                helper: 'Units consumed in window',
            },
            {
                key: 'totalForecast',
                label: 'Forecast demand',
                value: formatNumber(report?.aggregates.totalForecast ?? 0, {
                    maximumFractionDigits: 0,
                    fallback: '—',
                }),
                helper: 'Projected units across parts',
            },
            {
                key: 'avgDailyDemand',
                label: 'Avg daily demand',
                value: formatNumber(report?.aggregates.avgDailyDemand ?? 0, {
                    maximumFractionDigits: 1,
                    fallback: '—',
                }),
                helper: 'Rolling mean consumption',
            },
            {
                key: 'mape',
                label: 'MAPE',
                value: formatPercent(report?.aggregates.mape ?? 0),
                helper: 'Forecast accuracy',
            },
            {
                key: 'mae',
                label: 'MAE',
                value: formatNumber(report?.aggregates.mae ?? 0, {
                    maximumFractionDigits: 1,
                    fallback: '—',
                }),
                helper: 'Mean absolute error',
            },
            {
                key: 'recommendedReorderPoint',
                label: 'Recommended reorder',
                value: formatNumber(
                    report?.aggregates.recommendedReorderPoint ?? 0,
                    {
                        maximumFractionDigits: 0,
                        fallback: '—',
                    },
                ),
                helper: 'System suggested trigger',
            },
            {
                key: 'recommendedSafetyStock',
                label: 'Recommended safety stock',
                value: formatNumber(
                    report?.aggregates.recommendedSafetyStock ?? 0,
                    {
                        maximumFractionDigits: 0,
                        fallback: '—',
                    },
                ),
                helper: 'Buffer to absorb volatility',
            },
        ],
        [report?.aggregates, formatNumber],
    );

    const isLoading =
        forecastQuery.isLoading || forecastQuery.isPlaceholderData;
    const hasData = Boolean(
        report && (report.table.length > 0 || report.series.length > 0),
    );
    const errorMessage = forecastQuery.isError
        ? buildErrorMessage(forecastQuery.error)
        : null;

    const handleApplyFilters = useCallback(() => {
        setAppliedFilters(filterDraft);
    }, [filterDraft]);

    const handleResetFilters = useCallback(() => {
        const defaults = createDefaultFilters();
        setFilterDraft(defaults);
        setAppliedFilters(defaults);
    }, []);

    const handleFilterChange = useCallback(
        <K extends keyof FilterFormState>(
            key: K,
            value: FilterFormState[K],
        ) => {
            setFilterDraft((prev) => ({ ...prev, [key]: value }));
        },
        [],
    );

    const handleSortChange = useCallback((columnKey: string) => {
        if (!isForecastSortableKey(columnKey)) {
            return;
        }

        const nextKey = columnKey as ForecastSortableKey;
        setTableSort((prev) => {
            if (prev.key === nextKey) {
                return {
                    key: nextKey,
                    direction: prev.direction === 'asc' ? 'desc' : 'asc',
                };
            }
            return { key: nextKey, direction: 'desc' };
        });
    }, []);

    if (!analyticsEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory Forecast</title>
                </Helmet>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Inventory forecast
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Unlock AI summaries, seasonality detection, and reorder
                        proposals by upgrading to a plan with analytics access.
                    </p>
                </div>
                <PlanUpgradeBanner />
                <EmptyState
                    icon={<AlertTriangle className="h-6 w-6" />}
                    title="Forecasting is locked"
                    description="Your plan does not include the analytics workspace yet. Upgrade to run demand forecasting and supplier scorecards."
                    ctaLabel="View billing"
                    ctaProps={{
                        variant: 'outline',
                        onClick: () => navigate('/app/settings/billing'),
                    }}
                />
            </div>
        );
    }

    if (planForbidden) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory Forecast</title>
                </Helmet>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Inventory forecast
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Your workspace plan currently blocks analytics. Visit
                        billing to restore AI summaries and forecast accuracy
                        dashboards.
                    </p>
                </div>
                <PlanUpgradeBanner />
                <EmptyState
                    icon={<AlertTriangle className="h-6 w-6" />}
                    title="Plan upgrade required"
                    description="Analytics features are disabled until your company upgrades to a plan that includes forecasting."
                    ctaLabel="View billing"
                    ctaProps={{
                        variant: 'outline',
                        onClick: () => navigate('/app/settings/billing'),
                    }}
                />
            </div>
        );
    }

    if (permissionDenied) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory Forecast</title>
                </Helmet>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Inventory forecast
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        You&apos;re signed in, but your role is missing the View
                        forecast reports permission.
                    </p>
                </div>
                <EmptyState
                    icon={<ShieldAlert className="h-6 w-6" />}
                    title="Access denied"
                    description="Ask a workspace admin to grant the View forecast reports permission before running analytics."
                    ctaLabel="Manage members"
                    ctaProps={{
                        variant: 'outline',
                        onClick: () => navigate('/app/settings/members'),
                    }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Inventory Forecast</title>
            </Helmet>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Inventory forecast
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Monitor demand accuracy, AI reorder guidance, and
                        item-level deltas to course-correct your replenishment
                        plan before shortages land.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link to="/app/analytics">Back to overview</Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => forecastQuery.refetch()}
                        disabled={forecastQuery.isFetching}
                    >
                        <RefreshCcw className="mr-2 h-4 w-4" /> Refresh
                    </Button>
                </div>
            </div>

            <PlanUpgradeBanner />

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <Filter className="h-4 w-4" /> Filters
                    </div>
                    <CardTitle className="text-base">
                        Configure the forecast window
                    </CardTitle>
                    <CardDescription>
                        Focus the AI summary and charts on the SKUs and
                        locations you care about.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    <div className="space-y-2">
                        <Label htmlFor="start-date">Start date</Label>
                        <Input
                            id="start-date"
                            type="date"
                            value={filterDraft.startDate}
                            onChange={(event) =>
                                handleFilterChange(
                                    'startDate',
                                    event.target.value,
                                )
                            }
                            max={filterDraft.endDate}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="end-date">End date</Label>
                        <Input
                            id="end-date"
                            type="date"
                            value={filterDraft.endDate}
                            onChange={(event) =>
                                handleFilterChange(
                                    'endDate',
                                    event.target.value,
                                )
                            }
                            min={filterDraft.startDate}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Parts</Label>
                        <FilterMultiSelect
                            placeholder="All parts"
                            options={partOptions}
                            value={filterDraft.partIds}
                            onChange={(value) =>
                                handleFilterChange('partIds', value)
                            }
                            loading={itemsQuery.isLoading}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Locations</Label>
                        <FilterMultiSelect
                            placeholder="All locations"
                            options={locationOptions}
                            value={filterDraft.locationIds}
                            onChange={(value) =>
                                handleFilterChange('locationIds', value)
                            }
                            loading={locationsQuery.isLoading}
                        />
                    </div>
                    <div className="space-y-2 lg:col-span-2 xl:col-span-1">
                        <Label>Categories</Label>
                        <CategoryTokenInput
                            value={filterDraft.categoryIds}
                            onChange={(value) =>
                                handleFilterChange('categoryIds', value)
                            }
                            suggestions={categorySuggestions}
                        />
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-2 lg:col-span-2 xl:col-span-3">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={handleResetFilters}
                        >
                            Reset
                        </Button>
                        <Button type="button" onClick={handleApplyFilters}>
                            Apply filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Alert className="bg-muted/40">
                <AlertDescription>
                    {filterChips.length ? (
                        <div className="mt-2 flex flex-wrap gap-2">
                            {filterChips.map((chip) => (
                                <Badge
                                    key={chip.key}
                                    variant="outline"
                                    className="text-xs font-medium"
                                >
                                    {chip.label}
                                </Badge>
                            ))}
                        </div>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            Using the default 90 day window across every SKU and
                            location.
                        </span>
                    )}
                </AlertDescription>
            </Alert>

            {forecastQuery.isError ? (
                <EmptyState
                    icon={<AlertTriangle className="h-6 w-6" />}
                    title="Unable to build the forecast"
                    description={
                        errorMessage ??
                        'The analytics service did not return a forecast report. Try again shortly.'
                    }
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => forecastQuery.refetch() }}
                />
            ) : (
                <div className="flex flex-col gap-6">
                    <div className="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                        <Card className="h-full">
                            <CardHeader className="space-y-1">
                                <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <Sparkles className="h-4 w-4" /> AI synopsis
                                </div>
                                <CardTitle className="text-base">
                                    Demand storyline
                                </CardTitle>
                                <CardDescription>
                                    Narrative summary powered by{' '}
                                    {summary?.provider ??
                                        'deterministic heuristics'}{' '}
                                    using the selected filters.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {isLoading ? (
                                    <Skeleton className="h-32 w-full" />
                                ) : summary &&
                                  (summary.summaryMarkdown ||
                                      summary.bullets.length) ? (
                                    <div className="space-y-4">
                                        {summary.summaryMarkdown ? (
                                            <p className="text-sm text-foreground/90">
                                                {stripMarkdown(
                                                    summary.summaryMarkdown,
                                                )}
                                            </p>
                                        ) : null}
                                        {summary.bullets.length ? (
                                            <ul className="list-disc space-y-2 pl-4 text-sm text-muted-foreground">
                                                {summary.bullets.map(
                                                    (bullet, index) => (
                                                        <li
                                                            key={`${bullet}-${index}`}
                                                        >
                                                            {bullet}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        ) : null}
                                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                            <Badge
                                                variant="secondary"
                                                className="bg-secondary/40 text-secondary-foreground"
                                            >
                                                {summary.provider}
                                            </Badge>
                                            <span className="text-muted-foreground/80">
                                                Source: {summary.source}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No AI notes yet. Run a new forecast to
                                        populate the storyline.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                        <Card className="h-full">
                            <CardHeader className="space-y-1">
                                <CardTitle className="text-base">
                                    Aggregates
                                </CardTitle>
                                <CardDescription>
                                    Blended demand and error metrics for the
                                    current filters.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {aggregateStats.map((stat) => (
                                    <div
                                        key={stat.key}
                                        className="rounded-lg border border-sidebar-border/50 bg-muted/30 p-3"
                                    >
                                        <p className="text-xs text-muted-foreground">
                                            {stat.label}
                                        </p>
                                        <p className="text-lg font-semibold text-foreground">
                                            {stat.value}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {stat.helper}
                                        </p>
                                    </div>
                                ))}
                                <ConfidenceBadge
                                    mape={report?.aggregates.mape ?? 0}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card className="h-full">
                            <CardHeader className="space-y-1">
                                <CardTitle className="text-base">
                                    Actual vs forecast trend
                                </CardTitle>
                                <CardDescription>
                                    Compare observed units against the model
                                    output in the selected window.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ForecastLineChart
                                    series={report?.series ?? []}
                                    isLoading={isLoading}
                                    labelFormatter={(value) =>
                                        formatDate(value, {
                                            month: 'short',
                                            day: 'numeric',
                                        })
                                    }
                                    valueFormatter={(value) =>
                                        formatNumber(value, {
                                            maximumFractionDigits: 0,
                                        })
                                    }
                                />
                            </CardContent>
                        </Card>
                        <MiniChart
                            title="Variance by part"
                            description="Top parts ranked by absolute variance between forecast and actuals."
                            data={varianceData}
                            series={[
                                {
                                    key: 'actual',
                                    label: 'Actual',
                                    type: 'bar',
                                    color: '#16a34a',
                                },
                                {
                                    key: 'forecast',
                                    label: 'Forecast',
                                    type: 'bar',
                                    color: '#2563eb',
                                },
                            ]}
                            valueFormatter={(value) =>
                                formatNumber(value, {
                                    maximumFractionDigits: 0,
                                })
                            }
                            isLoading={isLoading}
                            height={280}
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle className="text-base">
                                        Part-level breakdown
                                    </CardTitle>
                                    <CardDescription>
                                        Forecast accuracy, reorder guidance, and
                                        safety stock per SKU.
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant="secondary"
                                    className="flex items-center gap-1 text-xs"
                                >
                                    <CalendarRange className="h-3.5 w-3.5" />
                                    {BUCKET_LABELS[
                                        report?.filtersUsed.bucket ??
                                            DEFAULT_FILTER_BUCKET
                                    ] ?? 'Daily buckets'}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <MetricsTable
                                rows={tableRows}
                                columns={buildColumns(formatNumber)}
                                isLoading={isLoading && tableRows.length === 0}
                                emptyState={
                                    hasData ? (
                                        <p className="text-sm text-muted-foreground">
                                            No rows match the applied filters.
                                        </p>
                                    ) : (
                                        <EmptyState
                                            icon={
                                                <TrendingUp className="h-5 w-5" />
                                            }
                                            title="No parts to compare"
                                            description="Upload usage history or broaden your filters to populate the forecast table."
                                        />
                                    )
                                }
                                sortKey={tableSort.key}
                                sortDirection={tableSort.direction}
                                onSortChange={handleSortChange}
                            />
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>
    );
}

function buildColumns(
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'],
): Array<DataTableColumn<EnrichedForecastRow>> {
    return [
        {
            key: 'partName',
            title: 'Part',
            render: (row) => (
                <div>
                    <p className="font-medium text-foreground">
                        {row.partName}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        ID #{row.partId}
                    </p>
                </div>
            ),
        },
        {
            key: 'totalActual',
            title: 'Actual demand',
            align: 'right',
            render: (row) => (
                <span className="font-medium">
                    {formatNumber(row.totalActual, {
                        maximumFractionDigits: 0,
                    })}
                </span>
            ),
        },
        {
            key: 'totalForecast',
            title: 'Forecast demand',
            align: 'right',
            render: (row) =>
                formatNumber(row.totalForecast, { maximumFractionDigits: 0 }),
        },
        {
            key: 'variance',
            title: 'Variance',
            align: 'right',
            render: (row) => (
                <span
                    className={cn(
                        'font-medium',
                        row.variance >= 0
                            ? 'text-emerald-600'
                            : 'text-destructive',
                    )}
                >
                    {formatNumber(row.variance, { maximumFractionDigits: 0 })}
                </span>
            ),
        },
        {
            key: 'mape',
            title: 'MAPE',
            align: 'right',
            render: (row) => <AccuracyTag value={row.mape} />,
        },
        {
            key: 'mae',
            title: 'MAE',
            align: 'right',
            render: (row) =>
                formatNumber(row.mae, { maximumFractionDigits: 1 }),
        },
        {
            key: 'reorderPoint',
            title: 'Reorder point',
            align: 'right',
            render: (row) =>
                formatNumber(row.reorderPoint, { maximumFractionDigits: 0 }),
        },
        {
            key: 'safetyStock',
            title: 'Safety stock',
            align: 'right',
            render: (row) =>
                formatNumber(row.safetyStock, { maximumFractionDigits: 0 }),
        },
    ];
}

function buildFilterChips(
    filters: FilterFormState,
    partLookup: Map<number, string>,
    locationLookup: Map<number, string>,
): FilterSummaryChip[] {
    const chips: FilterSummaryChip[] = [];

    if (filters.startDate || filters.endDate) {
        const label = [filters.startDate, filters.endDate]
            .filter(Boolean)
            .join(' → ');
        chips.push({ key: 'date-range', label: label || 'Custom range' });
    }

    if (filters.partIds.length) {
        const names = filters.partIds
            .map((id) => {
                const numericId = Number(id);
                return (
                    partLookup.get(
                        Number.isFinite(numericId) ? numericId : -1,
                    ) ?? `Part ${id}`
                );
            })
            .slice(0, 3);
        const label =
            names.join(', ') +
            (filters.partIds.length > 3
                ? ` +${filters.partIds.length - 3}`
                : '');
        chips.push({ key: 'parts', label: `Parts: ${label}` });
    }

    if (filters.locationIds.length) {
        const names = filters.locationIds
            .map((id) => {
                const numericId = Number(id);
                return (
                    locationLookup.get(
                        Number.isFinite(numericId) ? numericId : -1,
                    ) ?? `Location ${id}`
                );
            })
            .slice(0, 3);
        const label =
            names.join(', ') +
            (filters.locationIds.length > 3
                ? ` +${filters.locationIds.length - 3}`
                : '');
        chips.push({ key: 'locations', label: `Locations: ${label}` });
    }

    if (filters.categoryIds.length) {
        const label = filters.categoryIds.slice(0, 3).join(', ');
        chips.push({
            key: 'categories',
            label: `Categories: ${label}${filters.categoryIds.length > 3 ? ` +${filters.categoryIds.length - 3}` : ''}`,
        });
    }

    return chips;
}

function buildVarianceComparison(rows: ForecastReportRow[]) {
    return [...rows]
        .sort(
            (a, b) =>
                Math.abs(b.totalActual - b.totalForecast) -
                Math.abs(a.totalActual - a.totalForecast),
        )
        .slice(0, 8)
        .map((row) => ({
            label: row.partName,
            actual: row.totalActual,
            forecast: row.totalForecast,
        }));
}

function buildErrorMessage(error: ApiError | null): string | null {
    if (!error) {
        return null;
    }

    if (typeof error.message === 'string' && error.message.length > 0) {
        return error.message;
    }

    return extractApiErrorDetail(error);
}

function extractApiErrorDetail(error: ApiError): string | null {
    if (!error.errors) {
        return null;
    }

    for (const entry of Object.values(
        error.errors as Record<string, unknown>,
    )) {
        if (typeof entry === 'string' && entry.trim().length > 0) {
            return entry;
        }

        if (Array.isArray(entry)) {
            const match = entry.find(
                (value): value is string =>
                    typeof value === 'string' && value.trim().length > 0,
            );
            if (match) {
                return match;
            }
        }
    }

    return null;
}

function mapFiltersToParams(filters: FilterFormState): ForecastReportParams {
    return {
        startDate: filters.startDate,
        endDate: filters.endDate,
        partIds: toNumericIds(filters.partIds),
        categoryIds: filters.categoryIds,
        locationIds: toNumericIds(filters.locationIds),
    };
}

function toNumericIds(values: string[]): number[] {
    return values
        .map((value) => Number(value))
        .filter((value) => Number.isFinite(value) && value > 0);
}

function createDefaultFilters(): FilterFormState {
    const today = new Date();
    const end = toInputDate(today);
    const startDate = new Date(today);
    startDate.setDate(startDate.getDate() - 90);

    return {
        startDate: toInputDate(startDate),
        endDate: end,
        partIds: [],
        categoryIds: [],
        locationIds: [],
    };
}

function toInputDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatPercent(value: number): string {
    if (!Number.isFinite(value)) {
        return '—';
    }
    return `${value.toFixed(1)}%`;
}

function sortByMetric(
    rowA: EnrichedForecastRow,
    rowB: EnrichedForecastRow,
    key: ForecastSortableKey,
    direction: 'asc' | 'desc',
) {
    const multiplier = direction === 'asc' ? 1 : -1;
    const valueA = rowA[key] ?? 0;
    const valueB = rowB[key] ?? 0;
    return (valueA - valueB) * multiplier;
}

function isForecastSortableKey(value: string): value is ForecastSortableKey {
    return (FORECAST_SORTABLE_KEYS as readonly string[]).includes(value);
}

function stripMarkdown(value: string): string {
    return value
        .replace(/[_*`#>-]/g, '')
        .replace(/\[(.*?)\]\((.*?)\)/g, '$1')
        .trim();
}

interface FilterMultiSelectProps {
    placeholder: string;
    options: FilterMultiSelectOption[];
    value: string[];
    onChange: (next: string[]) => void;
    loading?: boolean;
}

function FilterMultiSelect({
    placeholder,
    options,
    value,
    onChange,
    loading,
}: FilterMultiSelectProps) {
    const [searchTerm, setSearchTerm] = useState('');

    const filteredOptions = useMemo(() => {
        if (!searchTerm.trim()) {
            return options;
        }
        return options.filter((option) =>
            option.label.toLowerCase().includes(searchTerm.toLowerCase()),
        );
    }, [options, searchTerm]);

    const handleToggle = (item: string, checked: boolean) => {
        if (checked) {
            onChange(Array.from(new Set([...value, item])));
            return;
        }
        onChange(value.filter((entry) => entry !== item));
    };

    return (
        <div className="space-y-2">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full justify-between"
                    >
                        <span>
                            {value.length
                                ? `${value.length} selected`
                                : placeholder}
                        </span>
                        <ChevronDown className="h-4 w-4 text-muted-foreground" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    className="max-h-72 w-72 overflow-y-auto"
                >
                    <DropdownMenuLabel className="flex items-center gap-2">
                        <Input
                            placeholder="Search"
                            value={searchTerm}
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            className="h-8"
                        />
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {loading ? (
                        <div className="flex items-center gap-2 px-2 py-1.5 text-sm text-muted-foreground">
                            <Loader2 className="h-4 w-4 animate-spin" /> Loading
                            options…
                        </div>
                    ) : filteredOptions.length === 0 ? (
                        <p className="px-2 py-1.5 text-sm text-muted-foreground">
                            No matches.
                        </p>
                    ) : (
                        filteredOptions.map((option) => (
                            <DropdownMenuCheckboxItem
                                key={option.value}
                                checked={value.includes(option.value)}
                                onCheckedChange={(checked) =>
                                    handleToggle(option.value, Boolean(checked))
                                }
                                className="flex items-start gap-2"
                            >
                                <div className="flex flex-col">
                                    <span className="text-sm font-medium text-foreground">
                                        {option.label}
                                    </span>
                                    {option.description ? (
                                        <span className="text-xs text-muted-foreground">
                                            {option.description}
                                        </span>
                                    ) : null}
                                </div>
                            </DropdownMenuCheckboxItem>
                        ))
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            {value.length ? (
                <div className="flex flex-wrap gap-2">
                    {value.map((selected) => (
                        <Badge
                            key={selected}
                            variant="outline"
                            className="gap-1"
                        >
                            <span>
                                {options.find(
                                    (option) => option.value === selected,
                                )?.label ?? selected}
                            </span>
                            <button
                                type="button"
                                onClick={() => handleToggle(selected, false)}
                                className="text-muted-foreground transition-colors hover:text-foreground"
                                aria-label="Remove selection"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            ) : (
                <p className="text-xs text-muted-foreground">
                    No filters applied.
                </p>
            )}
        </div>
    );
}

interface CategoryTokenInputProps {
    value: string[];
    onChange: (next: string[]) => void;
    suggestions?: string[];
}

function CategoryTokenInput({
    value,
    onChange,
    suggestions = [],
}: CategoryTokenInputProps) {
    const [input, setInput] = useState('');

    const handleAdd = () => {
        const trimmed = input.trim();
        if (!trimmed) {
            return;
        }
        onChange(Array.from(new Set([...value, trimmed])));
        setInput('');
    };

    const handleRemove = (token: string) => {
        onChange(value.filter((entry) => entry !== token));
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleAdd();
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <Input
                    value={input}
                    onChange={(event) => setInput(event.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Type a category and press Enter"
                />
                <Button
                    type="button"
                    variant="secondary"
                    onClick={handleAdd}
                    disabled={!input.trim()}
                >
                    Add
                </Button>
            </div>
            {value.length ? (
                <div className="flex flex-wrap gap-2">
                    {value.map((token) => (
                        <Badge key={token} variant="outline" className="gap-1">
                            <span>{token}</span>
                            <button
                                type="button"
                                onClick={() => handleRemove(token)}
                                className="text-muted-foreground transition-colors hover:text-foreground"
                                aria-label={`Remove ${token}`}
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            ) : (
                <p className="text-xs text-muted-foreground">
                    All categories included.
                </p>
            )}
            {suggestions.length ? (
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <span>Suggestions:</span>
                    {suggestions.slice(0, 6).map((suggestion) => (
                        <button
                            key={suggestion}
                            type="button"
                            onClick={() =>
                                onChange(
                                    Array.from(new Set([...value, suggestion])),
                                )
                            }
                            className="rounded-full border border-dashed border-sidebar-border/60 px-2 py-1 text-foreground transition-colors hover:bg-muted/50"
                        >
                            {suggestion}
                        </button>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

interface AccuracyTagProps {
    value: number;
}

function AccuracyTag({ value }: AccuracyTagProps) {
    const descriptor = getConfidenceDescriptor(value);
    return (
        <Badge className={descriptor.className} variant="outline">
            {descriptor.label}
        </Badge>
    );
}

interface ConfidenceBadgeProps {
    mape: number;
}

function ConfidenceBadge({ mape }: ConfidenceBadgeProps) {
    const descriptor = getConfidenceDescriptor(mape);

    return (
        <div className="rounded-lg border border-sidebar-border/50 bg-background/60 p-3 text-sm">
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                Confidence
            </p>
            <div className="flex items-center gap-2">
                <Badge className={descriptor.className} variant="outline">
                    {descriptor.label}
                </Badge>
                <span className="text-xs text-muted-foreground">
                    MAPE {formatPercent(mape)}
                </span>
            </div>
        </div>
    );
}

function getConfidenceDescriptor(mape: number) {
    if (!Number.isFinite(mape)) {
        return {
            label: 'Unknown',
            className: 'border-muted text-muted-foreground',
        };
    }
    if (mape <= 10) {
        return {
            label: 'High confidence',
            className: 'border-emerald-600 text-emerald-700 bg-emerald-500/10',
        };
    }
    if (mape <= 20) {
        return {
            label: 'Moderate confidence',
            className: 'border-amber-500 text-amber-600 bg-amber-500/10',
        };
    }
    return {
        label: 'Low confidence',
        className: 'border-destructive text-destructive bg-destructive/10',
    };
}
