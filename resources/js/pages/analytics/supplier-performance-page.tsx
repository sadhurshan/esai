import { useCallback, useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    Filter,
    ShieldAlert,
    RefreshCcw,
    Sparkles,
    Users,
    X,
} from 'lucide-react';

import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import {
    useAnalyticsSupplierOptions,
    useSupplierPerformanceReport,
    type AnalyticsSupplierOption,
    type SupplierPerformanceReport,
    type SupplierPerformanceTableRow,
} from '@/hooks/api/analytics/use-analytics';
import { PerformanceMultiChart } from '@/components/analytics/performance-multi-chart';
import { MetricsTable } from '@/components/analytics/metrics-table';
import type { DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { isForbiddenError, type ApiError } from '@/lib/api';

interface FilterFormState {
    startDate: string;
    endDate: string;
    supplierId: string;
}

interface FilterChip {
    key: string;
    label: string;
}

const DEFAULT_RANGE_DAYS = 90;
type MetricValueType = 'number' | 'percentage' | 'duration';

const METRIC_TYPE_HINTS: Record<string, MetricValueType> = {
    onTimeDeliveryRate: 'percentage',
    defectRate: 'percentage',
    leadTimeVariance: 'duration',
    priceVolatility: 'percentage',
    serviceResponsiveness: 'percentage',
    riskScore: 'number',
};

const SUPPLIER_SORTABLE_KEYS = [
    'onTimeDeliveryRate',
    'defectRate',
    'leadTimeVariance',
    'priceVolatility',
    'serviceResponsiveness',
    'riskScore',
] as const;

type SupplierSortableKey = (typeof SUPPLIER_SORTABLE_KEYS)[number];
const SUPPLIER_PICKER_PAGE_SIZE = 50;

export function SupplierPerformancePage() {
    const { hasFeature, notifyPlanLimit, clearPlanLimit, activePersona } = useAuth();
    const navigate = useNavigate();
    const { formatNumber, formatDate } = useFormatting();
    const analyticsEnabled = hasFeature('analytics.access');
    const isSupplierPersona = activePersona?.type === 'supplier';
    const personaSupplierId = activePersona?.supplier_id ?? null;
    const personaSupplierName = activePersona?.supplier_name ?? activePersona?.company_name ?? null;

    const [filterDraft, setFilterDraft] = useState<FilterFormState>(() => createDefaultFilters(personaSupplierId));
    const [appliedFilters, setAppliedFilters] = useState<FilterFormState>(() => createDefaultFilters(personaSupplierId));
        const [tableSort, setTableSort] = useState<{ key: SupplierSortableKey; direction: 'asc' | 'desc' }>({
            key: 'onTimeDeliveryRate',
            direction: 'desc',
        });
    const [supplierSearchInput, setSupplierSearchInput] = useState('');
    const [supplierSearchTerm, setSupplierSearchTerm] = useState('');

    useEffect(() => {
        if (!analyticsEnabled) {
            notifyPlanLimit({
                featureKey: 'analytics.access',
                message: 'Upgrade your plan to unlock supplier performance analytics.',
            });

            return () => {
                clearPlanLimit();
            };
        }

        return undefined;
    }, [analyticsEnabled, clearPlanLimit, notifyPlanLimit]);

    useEffect(() => {
        if (!isSupplierPersona) {
            return;
        }

        const supplierId = personaSupplierId ? String(personaSupplierId) : '';
        setFilterDraft((prev) => (prev.supplierId === supplierId ? prev : { ...prev, supplierId }));
        setAppliedFilters((prev) => (prev.supplierId === supplierId ? prev : { ...prev, supplierId }));
    }, [isSupplierPersona, personaSupplierId]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            setSupplierSearchTerm(supplierSearchInput.trim());
        }, 250);

        return () => window.clearTimeout(handle);
    }, [supplierSearchInput]);

    const supplierOptionsQuery = useAnalyticsSupplierOptions({
        search: supplierSearchTerm || undefined,
        perPage: SUPPLIER_PICKER_PAGE_SIZE,
        selectedId: filterDraft.supplierId,
        enabled: analyticsEnabled && !isSupplierPersona,
    });

    const supplierOptions = useMemo(
        () => mapSupplierOptions(supplierOptionsQuery.data ?? []),
        [supplierOptionsQuery.data],
    );

    const supplierIdForQuery = useMemo(() => {
        if (isSupplierPersona) {
            return personaSupplierId ? String(personaSupplierId) : '';
        }
        return appliedFilters.supplierId;
    }, [appliedFilters.supplierId, isSupplierPersona, personaSupplierId]);

    const supplierIdNumeric = toNumericId(supplierIdForQuery);
    const queryEnabled = analyticsEnabled && supplierIdNumeric !== null;

    const performanceQuery = useSupplierPerformanceReport(
        {
            startDate: appliedFilters.startDate,
            endDate: appliedFilters.endDate,
            supplierId: supplierIdNumeric,
        },
        { enabled: queryEnabled },
    );
    const planForbidden =
        performanceQuery.isError && isForbiddenError(performanceQuery.error, 'analytics_upgrade_required');
    const permissionDenied =
        performanceQuery.isError && isForbiddenError(performanceQuery.error, 'supplier_performance_permission_required');

    const report = performanceQuery.data?.report;
    const summary = performanceQuery.data?.summary;

    const performanceSeries = report?.series ?? [];

    const tableRows = useMemo(() => {
        const rows = report?.table ?? [];
        return [...rows].sort((a, b) => sortByMetric(a, b, tableSort.key, tableSort.direction));
    }, [report?.table, tableSort]);

    const filterChips = useMemo(() => {
        return buildFilterChips(
            appliedFilters,
            isSupplierPersona ? personaSupplierName : lookupSupplierName(appliedFilters.supplierId, supplierOptions),
        );
    }, [appliedFilters, isSupplierPersona, personaSupplierName, supplierOptions]);

    const keyMetrics = useMemo(() => buildKeyMetrics(report, formatNumber), [report, formatNumber]);

    const isLoading = performanceQuery.isLoading || performanceQuery.isPlaceholderData;
    const hasData = Boolean(report && (report.series.length > 0 || report.table.length > 0));
    const errorMessage = performanceQuery.isError ? buildErrorMessage(performanceQuery.error) : null;

    const handleApply = useCallback(() => {
        setAppliedFilters(filterDraft);
    }, [filterDraft]);

    const handleReset = useCallback(() => {
        const defaults = createDefaultFilters(isSupplierPersona ? personaSupplierId : null);
        setFilterDraft(defaults);
        setAppliedFilters(defaults);
    }, [isSupplierPersona, personaSupplierId]);

    const handleFilterChange = useCallback(<K extends keyof FilterFormState>(key: K, value: FilterFormState[K]) => {
        setFilterDraft((prev) => ({ ...prev, [key]: value }));
    }, []);

    const handleSortChange = useCallback((columnKey: string) => {
        if (!isSupplierSortableKey(columnKey)) {
            return;
        }

        const nextKey = columnKey as SupplierSortableKey;
        setTableSort((prev) => {
            if (prev.key === nextKey) {
                return { key: nextKey, direction: prev.direction === 'asc' ? 'desc' : 'asc' };
            }
            return { key: nextKey, direction: 'desc' };
        });
    }, []);

    if (!analyticsEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier Performance</title>
                </Helmet>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Supplier performance</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Analyze on-time delivery, defect rates, and risk only after enabling analytics in your plan.
                    </p>
                </div>
                <PlanUpgradeBanner />
                <EmptyState
                    icon={<AlertTriangle className="h-6 w-6" />}
                    title="Analytics required"
                    description="Upgrade your workspace plan to unlock supplier performance reports."
                    ctaLabel="View billing"
                    ctaProps={{ variant: 'outline', onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    if (planForbidden) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier Performance</title>
                </Helmet>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Supplier performance</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Supplier analytics are disabled on the current plan. Upgrade to unlock KPI scorecards and AI
                        narratives.
                    </p>
                </div>
                <PlanUpgradeBanner />
                <EmptyState
                    icon={<AlertTriangle className="h-6 w-6" />}
                    title="Plan upgrade required"
                    description="Move to a plan with analytics enabled to review supplier performance metrics."
                    ctaLabel="View billing"
                    ctaProps={{ variant: 'outline', onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    if (permissionDenied) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier Performance</title>
                </Helmet>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Supplier performance</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Your role is missing the View supplier performance permission required to open this workspace.
                    </p>
                </div>
                <EmptyState
                    icon={<ShieldAlert className="h-6 w-6" />}
                    title="Access denied"
                    description="Ask an administrator to grant the View supplier performance permission before reloading this page."
                    ctaLabel="Manage members"
                    ctaProps={{ variant: 'outline', onClick: () => navigate('/app/settings/members') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier Performance</title>
            </Helmet>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Supplier performance</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Track quality, delivery, responsiveness, and price signals with AI summaries tailored to each supplier.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link to="/app/analytics">Back to overview</Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => performanceQuery.refetch()}
                        disabled={performanceQuery.isFetching || !queryEnabled}
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
                    <CardTitle className="text-base">Configure the reporting window</CardTitle>
                    <CardDescription>Narrow the analysis by time range and, if needed, supplier.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    <div className="space-y-2">
                        <Label htmlFor="start-date">Start date</Label>
                        <Input
                            id="start-date"
                            type="date"
                            value={filterDraft.startDate}
                            onChange={(event) => handleFilterChange('startDate', event.target.value)}
                            max={filterDraft.endDate}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="end-date">End date</Label>
                        <Input
                            id="end-date"
                            type="date"
                            value={filterDraft.endDate}
                            onChange={(event) => handleFilterChange('endDate', event.target.value)}
                            min={filterDraft.startDate}
                        />
                    </div>
                    {isSupplierPersona ? (
                        <div className="space-y-2">
                            <Label>Supplier</Label>
                            <div className="rounded-md border border-dashed border-sidebar-border/60 px-3 py-2 text-sm text-muted-foreground">
                                {personaSupplierName ?? 'Your supplier profile'}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <Label htmlFor="supplier-search">Supplier</Label>
                            <Input
                                id="supplier-search"
                                placeholder="Search suppliers"
                                value={supplierSearchInput}
                                onChange={(event) => setSupplierSearchInput(event.target.value)}
                            />
                            <Select
                                value={filterDraft.supplierId}
                                onValueChange={(value) => handleFilterChange('supplierId', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choose a supplier" />
                                </SelectTrigger>
                                <SelectContent>
                                    {supplierOptions.length === 0 ? (
                                        <div className="px-2 py-1.5 text-sm text-muted-foreground">No suppliers found.</div>
                                    ) : (
                                        supplierOptions.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))
                                    )}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                    <div className="flex items-end justify-end gap-2">
                        <Button type="button" variant="ghost" onClick={handleReset}>
                            Reset
                        </Button>
                        <Button type="button" onClick={handleApply}>
                            Apply filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Alert className="bg-muted/40">
                <AlertTitle>Current filters</AlertTitle>
                <AlertDescription>
                    {filterChips.length ? (
                        <div className="mt-2 flex flex-wrap gap-2">
                            {filterChips.map((chip) => (
                                <Badge key={chip.key} variant="outline" className="text-xs font-medium">
                                    {chip.label}
                                </Badge>
                            ))}
                        </div>
                    ) : (
                        <span className="text-sm text-muted-foreground">Using the default 90-day window.</span>
                    )}
                </AlertDescription>
            </Alert>

            {!queryEnabled ? (
                <EmptyState
                    icon={<Users className="h-6 w-6" />}
                    title="Choose a supplier"
                    description="Pick a supplier to load performance metrics. Suppliers viewing their own workspace will see their company by default."
                    className="border-dashed bg-muted/20"
                />
            ) : performanceQuery.isError ? (
                <EmptyState
                    icon={<AlertTriangle className="h-6 w-6" />}
                    title="Unable to load report"
                    description={errorMessage ?? 'Something went wrong while fetching performance data.'}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => performanceQuery.refetch() }}
                />
            ) : (
                <div className="flex flex-col gap-6">
                    <div className="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                        <Card className="h-full">
                            <CardHeader className="space-y-1">
                                <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <Sparkles className="h-4 w-4" /> AI synopsis
                                </div>
                                <CardTitle className="text-base">Performance storyline</CardTitle>
                                <CardDescription>Generated for the selected supplier and date range.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {isLoading ? (
                                    <Skeleton className="h-32 w-full" />
                                ) : summary && (summary.summaryMarkdown || summary.bullets.length) ? (
                                    <div className="space-y-4">
                                        {summary.summaryMarkdown ? (
                                            <p className="text-sm text-foreground/90">{stripMarkdown(summary.summaryMarkdown)}</p>
                                        ) : null}
                                        {summary.bullets.length ? (
                                            <ul className="list-disc space-y-2 pl-4 text-sm text-muted-foreground">
                                                {summary.bullets.map((bullet, index) => (
                                                    <li key={`${bullet}-${index}`}>{bullet}</li>
                                                ))}
                                            </ul>
                                        ) : null}
                                        <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                            <Badge variant="secondary" className="bg-secondary/40 text-secondary-foreground">
                                                {summary.provider}
                                            </Badge>
                                            <span>Source: {summary.source}</span>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No AI summary yet. Apply filters and refresh.</p>
                                )}
                            </CardContent>
                        </Card>
                        <Card className="h-full">
                            <CardHeader className="space-y-1">
                                <CardTitle className="text-base">Key metrics</CardTitle>
                                <CardDescription>Aggregated values over the selected range.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {keyMetrics.map((metric) => (
                                    <div key={metric.key} className="rounded-lg border border-sidebar-border/50 bg-muted/30 p-3">
                                        <p className="text-xs text-muted-foreground">{metric.label}</p>
                                        <p className="text-lg font-semibold text-foreground">{metric.value}</p>
                                        <p className="text-xs text-muted-foreground">{metric.helper}</p>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader className="space-y-1">
                            <div className="flex items-center justify-between gap-2">
                                <div>
                                    <CardTitle className="text-base">Weekly performance trends</CardTitle>
                                    <CardDescription>Toggle metrics to focus on the most relevant signals.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <PerformanceMultiChart
                                series={performanceSeries}
                                isLoading={isLoading}
                                labelFormatter={(value) => formatDate(value, { month: 'short', day: 'numeric' })}
                                valueFormatter={(value, key) => formatMetricValue(value, key, formatNumber)}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle className="text-base">Supplier metrics table</CardTitle>
                                    <CardDescription>Accuracy, responsiveness, and risk snapshot.</CardDescription>
                                </div>
                                <Badge variant="secondary" className="flex items-center gap-1 text-xs">
                                    <BarChart3 className="h-3.5 w-3.5" /> {report?.filtersUsed.bucket ?? 'weekly'} cadence
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <MetricsTable
                                rows={tableRows}
                                columns={buildTableColumns(formatNumber)}
                                isLoading={isLoading && tableRows.length === 0}
                                emptyState={
                                    hasData ? (
                                        <p className="text-sm text-muted-foreground">No data for this supplier and window.</p>
                                    ) : (
                                        <EmptyState
                                            icon={<Activity className="h-5 w-5" />}
                                            title="No performance data"
                                            description="Once purchases and receipts are recorded this table will populate."
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

function createDefaultFilters(supplierId?: number | null): FilterFormState {
    const today = new Date();
    const end = toInputDate(today);
    const start = new Date(today);
    start.setDate(start.getDate() - DEFAULT_RANGE_DAYS);

    return {
        startDate: toInputDate(start),
        endDate: end,
        supplierId: supplierId ? String(supplierId) : '',
    };
}

function toInputDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function buildFilterChips(filters: FilterFormState, supplierName?: string | null): FilterChip[] {
    const chips: FilterChip[] = [];

    if (filters.startDate || filters.endDate) {
        chips.push({ key: 'dates', label: [filters.startDate, filters.endDate].filter(Boolean).join(' → ') });
    }

    if (supplierName) {
        chips.push({ key: 'supplier', label: supplierName });
    }

    return chips;
}

function mapSupplierOptions(items: AnalyticsSupplierOption[]): Array<{ value: string; label: string }> {
    return items.map((supplier) => ({
        value: String(supplier.id),
        label: supplier.name,
    }));
}

function lookupSupplierName(id: string, options: Array<{ value: string; label: string }>): string | null {
    const option = options.find((item) => item.value === id);
    return option?.label ?? null;
}

function toNumericId(value: string): number | null {
    if (!value) {
        return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function buildKeyMetrics(report: SupplierPerformanceReport | undefined, formatNumber: ReturnType<typeof useFormatting>['formatNumber']) {
    const row = report?.table?.[0];
    if (!row) {
        return [];
    }

    return [
        {
            key: 'on-time',
            label: 'On-time delivery rate',
            value: formatPercentValue(row.onTimeDeliveryRate),
            helper: 'Share of shipments that met promise',
        },
        {
            key: 'defect',
            label: 'Defect rate',
            value: formatPercentValue(row.defectRate),
            helper: 'Lines flagged with quality issues',
        },
        {
            key: 'lead-variance',
            label: 'Lead time variance',
            value: `${formatNumber(row.leadTimeVariance ?? 0, { maximumFractionDigits: 1 })}d`,
            helper: 'Volatility against negotiated lead time',
        },
        {
            key: 'price-vol',
            label: 'Price volatility',
            value: formatPercentValue(row.priceVolatility),
            helper: 'Unit price movement over time',
        },
        {
            key: 'service',
            label: 'Service responsiveness',
            value: formatPercentValue(row.serviceResponsiveness),
            helper: 'Average SLA response rate',
        },
        {
            key: 'risk',
            label: 'Risk score',
            value: formatNumber(row.riskScore ?? 0, { maximumFractionDigits: 1 }),
            helper: row.riskCategory ? `Category ${row.riskCategory}` : 'No risk category',
        },
    ];
}

function buildTableColumns(formatNumber: ReturnType<typeof useFormatting>['formatNumber']): Array<DataTableColumn<SupplierPerformanceTableRow>> {
    return [
        {
            key: 'supplierName',
            title: 'Supplier',
            render: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium text-foreground">{row.supplierName ?? `Supplier #${row.supplierId ?? '—'}`}</span>
                    <span className="text-xs text-muted-foreground">ID {row.supplierId ?? '—'}</span>
                </div>
            ),
        },
        {
            key: 'onTimeDeliveryRate',
            title: 'On-time %',
            align: 'right',
            render: (row) => formatPercentValue(row.onTimeDeliveryRate),
        },
        {
            key: 'defectRate',
            title: 'Defect %',
            align: 'right',
            render: (row) => formatPercentValue(row.defectRate),
        },
        {
            key: 'leadTimeVariance',
            title: 'Lead variance',
            align: 'right',
            render: (row) => `${formatNumber(row.leadTimeVariance ?? 0, { maximumFractionDigits: 1 })}d`,
        },
        {
            key: 'priceVolatility',
            title: 'Price volatility',
            align: 'right',
            render: (row) => formatPercentValue(row.priceVolatility),
        },
        {
            key: 'serviceResponsiveness',
            title: 'Service responsiveness',
            align: 'right',
            render: (row) => formatPercentValue(row.serviceResponsiveness),
        },
        {
            key: 'riskScore',
            title: 'Risk score',
            align: 'right',
            render: (row) => formatNumber(row.riskScore ?? 0, { maximumFractionDigits: 1 }),
        },
        {
            key: 'riskCategory',
            title: 'Risk category',
            render: (row) => row.riskCategory ?? '—',
        },
    ];
}

function sortByMetric(
    rowA: SupplierPerformanceTableRow,
    rowB: SupplierPerformanceTableRow,
    key: SupplierSortableKey,
    direction: 'asc' | 'desc',
) {
    const multiplier = direction === 'asc' ? 1 : -1;
    const valueA = rowA[key] ?? 0;
    const valueB = rowB[key] ?? 0;
    return (valueA - valueB) * multiplier;
}

function formatPercentValue(value: number): string {
    if (!Number.isFinite(value)) {
        return '—';
    }

    const normalized = value <= 1 ? value * 100 : value;
    return `${normalized.toFixed(1)}%`;
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

function isSupplierSortableKey(value: string): value is SupplierSortableKey {
    return (SUPPLIER_SORTABLE_KEYS as readonly string[]).includes(value);
}

function extractApiErrorDetail(error: ApiError): string | null {
    if (!error.errors) {
        return null;
    }

    for (const entry of Object.values(error.errors as Record<string, unknown>)) {
        if (typeof entry === 'string' && entry.trim().length > 0) {
            return entry;
        }

        if (Array.isArray(entry)) {
            const match = entry.find((value): value is string => typeof value === 'string' && value.trim().length > 0);
            if (match) {
                return match;
            }
        }
    }

    return null;
}

function stripMarkdown(value: string): string {
    return value
        .replace(/[_*`#>-]/g, '')
        .replace(/\[(.*?)\]\((.*?)\)/g, '$1')
        .trim();
}

function formatMetricValue(
    value: number,
    key: string,
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'],
): string {
    const type = METRIC_TYPE_HINTS[key] ?? 'number';

    if (type === 'percentage') {
        return formatPercentValue(value);
    }

    if (type === 'duration') {
        return `${formatNumber(value, { maximumFractionDigits: 1 })}d`;
    }

    return formatNumber(value, { maximumFractionDigits: 1 });
}
