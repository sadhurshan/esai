import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Activity, AlertTriangle, BarChart2, Brain, Gauge, History, ShieldAlert, TrendingUp } from 'lucide-react';

import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/empty-state';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useAiModelMetrics } from '@/hooks/api/admin/use-ai-model-metrics';
import { useAiTrainingJobs } from '@/hooks/api/admin/use-ai-training-jobs';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { AiModelMetricEntry, AiModelMetricFilters, ModelTrainingJob } from '@/types/admin';
import { cn } from '@/lib/utils';

const DEFAULT_LOOKBACK_DAYS = 45;
const SUMMARY_METRICS = ['mape', 'mae', 'f1', 'error_rate'] as const;
const FEATURE_OPTIONS = [
    { label: 'Forecast accuracy', value: 'forecast' },
    { label: 'Supplier risk calibration', value: 'supplier_risk' },
];
const METRIC_THRESHOLDS: Record<
    string,
    {
        helper: string;
        variant: 'percent' | 'number';
        precision?: number;
        warnAbove?: number;
        warnBelow?: number;
    }
> = {
    mape: { helper: 'Target under 35%', variant: 'percent', precision: 1, warnAbove: 0.35 },
    mae: { helper: 'Tracks absolute demand error', variant: 'number', precision: 1 },
    f1: { helper: 'Balance of precision/recall (min 0.75)', variant: 'number', precision: 2, warnBelow: 0.75 },
    error_rate: { helper: 'Share of failed predictions', variant: 'percent', precision: 1, warnAbove: 0.2 },
};

const RISK_BUCKET_DISPLAY: Record<string, string> = {
    high: 'High risk',
    medium: 'Medium risk',
    low: 'Low risk',
};

type TrainingFeature = 'forecast' | 'risk';

const TRAINING_FEATURE_LABELS: Record<TrainingFeature, string> = {
    forecast: 'Forecasting',
    risk: 'Supplier risk',
};

type FeatureValue = (typeof FEATURE_OPTIONS)[number]['value'];

type MetricsFormValues = {
    feature: FeatureValue;
    from: string;
    to: string;
};

const DEFAULT_FORM: MetricsFormValues = buildDefaultForm();

export function AdminAiModelHealthPage() {
    const { isAdmin } = useAuth();
    const { formatNumber, formatDate } = useFormatting();
    const [formValues, setFormValues] = useState<MetricsFormValues>(DEFAULT_FORM);
    const [filters, setFilters] = useState<AiModelMetricFilters>(() => ({
        feature: DEFAULT_FORM.feature,
        from: toIsoDate(DEFAULT_FORM.from),
        to: toIsoDate(DEFAULT_FORM.to),
        perPage: 200,
    }));
    const [selectedMetric, setSelectedMetric] = useState<string>('mape');

    const { data, isLoading, isFetching, refetch } = useAiModelMetrics(filters, { enabled: isAdmin });
    const normalizedFeature = normalizeFeatureValue(filters.feature);
    const trainingFeature = mapMetricsFeatureToTraining(normalizedFeature);
    const trainingJobFilters = useMemo(() => ({ feature: trainingFeature, perPage: 1 }), [trainingFeature]);
    const {
        data: trainingJobsData,
        isLoading: isTrainingLoading,
        isFetching: isTrainingFetching,
        refetch: refetchTrainingJobs,
    } = useAiTrainingJobs(trainingJobFilters, { enabled: isAdmin });
    const latestTrainingJob = trainingJobsData?.items?.[0] ?? null;

    useEffect(() => {
        if (!isAdmin) {
            return;
        }

        const available = deriveMetricNames(data?.items ?? []);
        if (available.length === 0) {
            return;
        }

        if (!available.includes(selectedMetric)) {
            setSelectedMetric(available[0]);
        }
    }, [data?.items, isAdmin, selectedMetric]);

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const entries = data?.items ?? [];
    const latestByMetric = buildLatestIndex(entries);
    const metricOptions = deriveMetricNames(entries);

    const trendPoints = useMemo(() => buildTrendSeries(entries, selectedMetric), [entries, selectedMetric]);
    const metricWarnings = buildWarningList(latestByMetric);
    const trainingWarnings = buildTrainingWarnings(latestTrainingJob);
    const warnings = [...metricWarnings, ...trainingWarnings];
    const supplierInsights = buildSupplierRiskInsights(entries);
    const trainingFeatureLabel = TRAINING_FEATURE_LABELS[trainingFeature];

    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setFilters({
            feature: formValues.feature,
            from: toIsoDate(formValues.from),
            to: toIsoDate(formValues.to, true),
            perPage: 200,
        });
    };

    const clearFilters = () => {
        setFormValues(DEFAULT_FORM);
        setFilters({
            feature: DEFAULT_FORM.feature,
            from: toIsoDate(DEFAULT_FORM.from),
            to: undefined,
            perPage: 200,
        });
        setSelectedMetric('mape');
    };

    const refresh = () => {
        void refetch();
        void refetchTrainingJobs();
    };

    return (
        <div className="space-y-8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <Heading
                    title="AI model health"
                    description="Track drift, accuracy, and calibration signals for every AI workload."
                />
                <div className="flex flex-wrap gap-2">
                    <Badge variant="outline" className="uppercase tracking-wide">
                        Observatory
                    </Badge>
                    <Button type="button" variant="outline" size="sm" onClick={refresh} disabled={isFetching}>
                        Refresh
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Filters</CardTitle>
                    <CardDescription>Scope metrics by feature and observation window.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={applyFilters}>
                        <div className="space-y-2">
                            <Label htmlFor="ai-feature">Feature</Label>
                            <Select
                                value={formValues.feature}
                                onValueChange={(value) => {
                                    setFormValues((prev) => ({ ...prev, feature: value }));
                                    if (value === 'supplier_risk' && !metricOptions.includes('risk_bucket_late_rate_high')) {
                                        setSelectedMetric('risk_bucket_late_rate_high');
                                    }
                                }}
                            >
                                <SelectTrigger id="ai-feature">
                                    <SelectValue placeholder="Select feature" />
                                </SelectTrigger>
                                <SelectContent>
                                    {FEATURE_OPTIONS.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="ai-from">From</Label>
                            <Input
                                id="ai-from"
                                type="date"
                                value={formValues.from}
                                onChange={(event) => setFormValues((prev) => ({ ...prev, from: event.target.value }))}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="ai-to">To</Label>
                            <Input
                                id="ai-to"
                                type="date"
                                value={formValues.to}
                                onChange={(event) => setFormValues((prev) => ({ ...prev, to: event.target.value }))}
                            />
                        </div>
                        <div className="flex items-end gap-2">
                            <Button type="button" variant="outline" onClick={clearFilters}>
                                Reset
                            </Button>
                            <Button type="submit">Apply</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            {warnings.length ? (
                <Alert variant="destructive">
                    <AlertTriangle className="h-5 w-5" aria-hidden />
                    <AlertTitle>Attention needed</AlertTitle>
                    <AlertDescription>
                        {warnings.map((warning) => (
                            <span key={warning} className="block">
                                {warning}
                            </span>
                        ))}
                    </AlertDescription>
                </Alert>
            ) : null}

            <TrainingTelemetrySection
                job={latestTrainingJob}
                featureLabel={trainingFeatureLabel}
                isLoading={isTrainingLoading || isTrainingFetching}
                formatDate={formatDate}
                formatNumber={formatNumber}
            />

            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {SUMMARY_METRICS.map((metric) => (
                    <SummaryMetricCard
                        key={metric}
                        metric={metric}
                        entry={latestByMetric.get(metric)}
                        formatNumber={formatNumber}
                    />
                ))}
            </section>

            <section className="grid gap-4 lg:grid-cols-2">
                <Card className="h-full">
                    <CardHeader className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>Trend</CardTitle>
                            <CardDescription>30-day trajectory for the selected metric.</CardDescription>
                        </div>
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Label htmlFor="metric-select" className="text-xs uppercase tracking-wide text-muted-foreground">
                                Metric
                            </Label>
                            <Select
                                value={selectedMetric}
                                onValueChange={setSelectedMetric}
                                disabled={metricOptions.length === 0}
                            >
                                <SelectTrigger id="metric-select" className="min-w-[220px]">
                                    <SelectValue placeholder="Select metric" />
                                </SelectTrigger>
                                <SelectContent>
                                    {metricOptions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {formatMetricLabel(option)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <Skeleton className="h-48 w-full" />
                        ) : trendPoints.length ? (
                            <MetricTrend
                                points={trendPoints}
                                formatNumber={formatNumber}
                                metric={selectedMetric}
                            />
                        ) : (
                            <EmptyState
                                icon={<BarChart2 className="h-8 w-8" aria-hidden />}
                                title="No observations yet"
                                description="Metrics will populate after the next evaluation job runs."
                            />
                        )}
                    </CardContent>
                </Card>
                <Card className="h-full">
                    <CardHeader>
                        <CardTitle>Recent observations</CardTitle>
                        <CardDescription>Latest {entries.length} rows returned by the API.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <Skeleton className="h-48 w-full" />
                        ) : entries.length ? (
                            <div className="space-y-3">
                                {entries.slice(0, 6).map((entry) => (
                                    <div key={entry.id} className="flex flex-col gap-1 rounded-lg border bg-muted/30 p-3 text-sm">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold text-foreground">{formatMetricLabel(entry.metric_name)}</span>
                                            <Badge variant="outline">{formatDate(entry.window_end ?? entry.created_at, { dateStyle: 'medium' })}</Badge>
                                        </div>
                                        <div className="text-lg font-semibold text-primary">
                                            {formatMetricValue(entry.metric_name, entry.metric_value, formatNumber)}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Feature: {entry.feature} • Company #{entry.company_id}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={<Activity className="h-8 w-8" aria-hidden />}
                                title="No rows returned"
                                description="Adjust your filters or wait for the nightly jobs to publish new samples."
                            />
                        )}
                    </CardContent>
                </Card>
            </section>

            {filters.feature === 'supplier_risk' ? (
                <SupplierRiskSection
                    insights={supplierInsights}
                    formatNumber={formatNumber}
                />
            ) : null}
        </div>
    );
}

function TrainingTelemetrySection({
    job,
    featureLabel,
    isLoading,
    formatDate,
    formatNumber,
}: {
    job: ModelTrainingJob | null;
    featureLabel: string;
    isLoading: boolean;
    formatDate: ReturnType<typeof useFormatting>['formatDate'];
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
}) {
    return (
        <section className="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <History className="h-5 w-5 text-muted-foreground" aria-hidden />
                        <div>
                            <CardTitle>Latest training run</CardTitle>
                            <CardDescription>{featureLabel} models</CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <Skeleton className="h-32 w-full" />
                    ) : job ? (
                        <div className="space-y-4">
                            {job.error_message ? (
                                <Alert variant="destructive">
                                    <AlertTitle>Job failed</AlertTitle>
                                    <AlertDescription>{job.error_message}</AlertDescription>
                                </Alert>
                            ) : null}
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <TrainingInfoStat label="Status" value={<TrainingStatusBadge status={job.status} />} />
                                <TrainingInfoStat label="Company" value={`#${job.company_id}`} />
                                <TrainingInfoStat label="Job" value={`#${job.id}`} />
                                <TrainingInfoStat
                                    label="Remote job"
                                    value={job.microservice_job_id ? job.microservice_job_id : '—'}
                                />
                                <TrainingInfoStat
                                    label="Started"
                                    value={formatDate(job.started_at, { dateStyle: 'medium', timeStyle: 'short' }) ?? '—'}
                                />
                                <TrainingInfoStat
                                    label="Finished"
                                    value={formatDate(job.finished_at, { dateStyle: 'medium', timeStyle: 'short' }) ?? '—'}
                                />
                                <TrainingInfoStat label="Duration" value={formatTrainingDuration(job)} />
                                <TrainingInfoStat
                                    label="Dataset"
                                    value={(job.parameters?.['dataset_upload_id'] as string | undefined) ?? '—'}
                                />
                            </dl>
                        </div>
                    ) : (
                        <EmptyState
                            icon={<History className="h-6 w-6" aria-hidden />}
                            title="No training runs yet"
                            description="Kick off a job from the console to populate telemetry."
                        />
                    )}
                </CardContent>
                <CardFooter>
                    <Button asChild variant="outline" size="sm">
                        <Link to="/app/admin/ai-training">Open training console</Link>
                    </Button>
                </CardFooter>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Result metrics</CardTitle>
                    <CardDescription>Highlights from the latest training artifact.</CardDescription>
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <Skeleton className="h-32 w-full" />
                    ) : job?.result ? (
                        <dl className="grid gap-3">
                            {extractTrainingMetricEntries(job).map(([key, value]) => (
                                <div key={key} className="flex items-center justify-between gap-3 rounded-md border bg-muted/30 px-3 py-2">
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">
                                        {formatMetricLabel(key)}
                                    </dt>
                                    <dd className="text-sm font-semibold text-foreground">
                                        {typeof value === 'number'
                                            ? formatMetricValue(key, value, formatNumber)
                                            : typeof value === 'string'
                                                ? value
                                                : '—'}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    ) : (
                        <EmptyState
                            icon={<Activity className="h-6 w-6" aria-hidden />}
                            title="Awaiting metrics"
                            description="Run a training job to capture calibration stats."
                        />
                    )}
                </CardContent>
            </Card>
        </section>
    );
}

function TrainingInfoStat({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
            <dd className="text-sm font-semibold text-foreground">{value}</dd>
        </div>
    );
}

function TrainingStatusBadge({ status }: { status?: string | null }) {
    if (!status) {
        return <Badge variant="outline">Unknown</Badge>;
    }

    if (status === 'running') {
        return <Badge variant="secondary">Running</Badge>;
    }

    if (status === 'failed') {
        return <Badge variant="destructive">Failed</Badge>;
    }

    if (status === 'completed') {
        return <Badge variant="outline" className="border-emerald-500/70 text-emerald-600">Completed</Badge>;
    }

    return <Badge variant="outline">{status}</Badge>;
}

function SummaryMetricCard({
    metric,
    entry,
    formatNumber,
}: {
    metric: (typeof SUMMARY_METRICS)[number];
    entry?: AiModelMetricEntry;
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
}) {
    const config = METRIC_THRESHOLDS[metric];
    const value = entry?.metric_value ?? null;
    const warning = config?.warnAbove !== undefined
        ? value !== null && value > config.warnAbove
        : config?.warnBelow !== undefined
            ? value !== null && value < config.warnBelow
            : false;

    return (
        <Card className={cn('border-l-4', warning ? 'border-l-rose-500' : 'border-l-emerald-500/70')}>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">{formatMetricLabel(metric)}</CardTitle>
                <CardDescription>{config?.helper ?? 'Latest snapshot'}</CardDescription>
            </CardHeader>
            <CardContent>
                {entry ? (
                    <div className="space-y-1">
                        <div className="text-3xl font-semibold">
                            {formatMetricValue(metric, value, formatNumber)}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Window ending {entry.window_end ? new Date(entry.window_end).toLocaleDateString() : '—'}
                        </p>
                    </div>
                ) : (
                    <div className="py-6 text-sm text-muted-foreground">Awaiting data</div>
                )}
                {warning ? (
                    <Badge variant="destructive" className="mt-2 w-fit gap-1">
                        <AlertTriangle className="h-3.5 w-3.5" aria-hidden /> Drift detected
                    </Badge>
                ) : null}
            </CardContent>
        </Card>
    );
}

function MetricTrend({
    points,
    formatNumber,
    metric,
}: {
    points: { label: string; value: number }[];
    metric: string;
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
}) {
    const max = Math.max(...points.map((point) => Math.abs(point.value)), 1);
    return (
        <div className="space-y-3">
            {points.map((point) => (
                <div key={point.label} className="flex items-center gap-3">
                    <span className="w-24 text-xs font-medium text-muted-foreground">{point.label}</span>
                    <div className="h-2 flex-1 rounded-full bg-muted/50">
                        <div
                            className="h-2 rounded-full bg-primary"
                            style={{ width: `${(Math.abs(point.value) / max) * 100}%` }}
                        />
                    </div>
                    <span className="w-20 text-right text-sm font-semibold text-foreground">
                        {formatMetricValue(metric, point.value, formatNumber)}
                    </span>
                </div>
            ))}
        </div>
    );
}

function SupplierRiskSection({
    insights,
    formatNumber,
}: {
    insights: SupplierRiskInsights;
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
}) {
    if (!insights.rows.length && insights.correlation === null) {
        return null;
    }

    return (
        <section className="space-y-4">
            <div className="flex items-center gap-3">
                <ShieldAlert className="h-5 w-5 text-muted-foreground" aria-hidden />
                <div>
                    <h3 className="text-base font-semibold">Supplier calibration</h3>
                    <p className="text-sm text-muted-foreground">
                        Rolling 30-day late and defect rates grouped by risk grade.
                    </p>
                </div>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Bucket health</CardTitle>
                        <CardDescription>Late vs defect performance per risk bucket.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {insights.rows.length ? (
                            <div className="space-y-3">
                                {insights.rows.map((row) => (
                                    <div key={row.bucket} className="rounded-lg border bg-muted/30 p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-sm font-semibold">{row.bucket}</span>
                                            <Badge variant="outline">{row.samples} samples</Badge>
                                        </div>
                                        <dl className="mt-3 grid gap-2 sm:grid-cols-2">
                                            <div>
                                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">Late rate</dt>
                                                <dd className="text-lg font-semibold text-primary">
                                                    {row.lateRate !== null
                                                        ? formatPercent(row.lateRate, formatNumber)
                                                        : '—'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">Defect rate</dt>
                                                <dd className="text-lg font-semibold text-primary">
                                                    {row.defectRate !== null
                                                        ? formatPercent(row.defectRate, formatNumber)
                                                        : '—'}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={<Gauge className="h-8 w-8" aria-hidden />}
                                title="No supplier outcomes captured"
                                description="Once goods receipts accumulate, bucket-level stats will appear here."
                            />
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Risk vs late correlation</CardTitle>
                        <CardDescription>Positive values mean higher risk suppliers are indeed late more often.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {insights.correlation !== null ? (
                            <div className="space-y-2">
                                <p className="text-4xl font-semibold text-foreground">
                                    {formatNumber(insights.correlation, { maximumFractionDigits: 2 })}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Pearson coefficient across {formatNumber(insights.correlationSamples, { maximumFractionDigits: 0 })}{' '}
                                    suppliers.
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Values above 0.4 indicate the risk model is aligned with reality; anything below 0.2 warrants review.
                                </p>
                            </div>
                        ) : (
                            <EmptyState
                                icon={<Brain className="h-8 w-8" aria-hidden />}
                                title="Not enough samples"
                                description="At least two suppliers with both scores and outcomes are required to compute a correlation."
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </section>
    );
}

type SupplierRiskInsights = {
    rows: Array<{ bucket: string; lateRate: number | null; defectRate: number | null; samples: number }>;
    correlation: number | null;
    correlationSamples: number;
};

function buildSupplierRiskInsights(entries: AiModelMetricEntry[]): SupplierRiskInsights {
    const rowsMap = new Map<string, { bucket: string; lateRate: number | null; defectRate: number | null; samples: number }>();
    let correlation: number | null = null;
    let correlationSamples = 0;

    for (const entry of entries) {
        if (entry.metric_name === 'risk_score_late_rate_correlation') {
            correlation = entry.metric_value ?? correlation;
            const sampleSize = Number(entry.notes?.sample_size ?? entry.notes?.count ?? 0);
            if (!Number.isNaN(sampleSize) && sampleSize > 0) {
                correlationSamples = sampleSize;
            }
            continue;
        }

        const match = entry.metric_name.match(/^risk_bucket_(late|defect)_rate_(low|medium|high)$/);
        if (!match) {
            continue;
        }

        const [, type, bucketKey] = match;
        const bucketLabel = RISK_BUCKET_DISPLAY[bucketKey] ?? bucketKey;
        const existing = rowsMap.get(bucketKey) ?? { bucket: bucketLabel, lateRate: null, defectRate: null, samples: 0 };

        if (entry.metric_value !== null && entry.metric_value !== undefined) {
            if (type === 'late') {
                existing.lateRate = entry.metric_value;
                existing.samples = Number(entry.notes?.sample_size ?? entry.notes?.sampleSize ?? existing.samples);
            } else {
                existing.defectRate = entry.metric_value;
            }
        }

        rowsMap.set(bucketKey, existing);
    }

    const rows = Array.from(rowsMap.values());

    return {
        rows,
        correlation,
        correlationSamples,
    };
}

function buildLatestIndex(entries: AiModelMetricEntry[]): Map<string, AiModelMetricEntry> {
    const sorted = [...entries].sort((a, b) =>
        new Date(b.window_end ?? b.updated_at ?? 0).getTime() - new Date(a.window_end ?? a.updated_at ?? 0).getTime(),
    );
    const map = new Map<string, AiModelMetricEntry>();

    for (const entry of sorted) {
        if (!map.has(entry.metric_name)) {
            map.set(entry.metric_name, entry);
        }
    }

    return map;
}

function deriveMetricNames(entries: AiModelMetricEntry[]): string[] {
    const names = new Set(entries.map((entry) => entry.metric_name));
    return Array.from(names).sort();
}

function buildTrendSeries(entries: AiModelMetricEntry[], metricName: string): { label: string; value: number }[] {
    if (!metricName) {
        return [];
    }

    const filtered = entries
        .filter((entry) => entry.metric_name === metricName && entry.metric_value !== null && entry.window_end)
        .sort((a, b) => new Date(a.window_end ?? a.updated_at ?? 0).getTime() - new Date(b.window_end ?? b.updated_at ?? 0).getTime())
        .slice(-20);

    return filtered.map((entry) => ({
        label: entry.window_end ? new Date(entry.window_end).toLocaleDateString() : '—',
        value: entry.metric_value ?? 0,
    }));
}

function buildWarningList(latestByMetric: Map<string, AiModelMetricEntry>): string[] {
    const warnings: string[] = [];

    SUMMARY_METRICS.forEach((metric) => {
        const config = METRIC_THRESHOLDS[metric];
        if (!config) {
            return;
        }

        const value = latestByMetric.get(metric)?.metric_value ?? null;
        if (value === null) {
            return;
        }

        if (config.warnAbove !== undefined && value > config.warnAbove) {
            warnings.push(`${formatMetricLabel(metric)} is elevated (${formatPercentMaybe(metric, value)}).`);
        }

        if (config.warnBelow !== undefined && value < config.warnBelow) {
            warnings.push(`${formatMetricLabel(metric)} fell below target (${formatPercentMaybe(metric, value)}).`);
        }
    });

    return warnings;
}

function buildTrainingWarnings(job: ModelTrainingJob | null): string[] {
    if (!job?.result) {
        return [];
    }

    const warnings: string[] = [];
    Object.entries(job.result).forEach(([key, rawValue]) => {
        const value = typeof rawValue === 'number' ? rawValue : null;
        if (value === null) {
            return;
        }

        const config = METRIC_THRESHOLDS[key];
        if (!config) {
            return;
        }

        if (config.warnAbove !== undefined && value > config.warnAbove) {
            warnings.push(`${formatMetricLabel(key)} (training) breached ${formatPercentMaybe(key, value)}`);
        }

        if (config.warnBelow !== undefined && value < config.warnBelow) {
            warnings.push(`${formatMetricLabel(key)} (training) dropped to ${formatPercentMaybe(key, value)}`);
        }
    });

    return warnings;
}

function extractTrainingMetricEntries(job: ModelTrainingJob | null): Array<[string, unknown]> {
    if (!job?.result) {
        return [];
    }

    return Object.entries(job.result).slice(0, 6);
}

function formatTrainingDuration(job: ModelTrainingJob): string {
    if (!job.started_at || !job.finished_at) {
        return '—';
    }

    const start = new Date(job.started_at).getTime();
    const end = new Date(job.finished_at).getTime();
    const minutes = Math.max(0, (end - start) / 60000);

    if (minutes < 1) {
        return `${Math.round(minutes * 60)}s`;
    }

    if (minutes < 60) {
        return `${minutes.toFixed(1)}m`;
    }

    return `${(minutes / 60).toFixed(1)}h`;
}

function normalizeFeatureValue(value?: string | null): FeatureValue {
    return isFeatureValue(value) ? value : DEFAULT_FORM.feature;
}

function isFeatureValue(value: unknown): value is FeatureValue {
    return FEATURE_OPTIONS.some((option) => option.value === value);
}

function mapMetricsFeatureToTraining(feature: FeatureValue): TrainingFeature {
    return feature === 'supplier_risk' ? 'risk' : 'forecast';
}

function formatMetricLabel(metric: string): string {
    if (metric === 'risk_score_late_rate_correlation') {
        return 'Risk vs late correlation';
    }

    const bucketMatch = metric.match(/^risk_bucket_(late|defect)_rate_(low|medium|high)$/);
    if (bucketMatch) {
        const [, type, bucketKey] = bucketMatch;
        const bucketLabel = RISK_BUCKET_DISPLAY[bucketKey] ?? bucketKey;
        const metricLabel = type === 'late' ? 'late rate' : 'defect rate';
        return `${bucketLabel} ${metricLabel}`;
    }

    return metric
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatMetricValue(
    metric: string,
    value: number | null | undefined,
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'],
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const config = METRIC_THRESHOLDS[metric];
    if (config?.variant === 'percent' || metric.includes('rate')) {
        return formatPercent(value, formatNumber, config?.precision ?? 1);
    }

    return formatNumber(value, {
        maximumFractionDigits: config?.precision ?? 2,
    });
}

function formatPercent(value: number, formatNumber: ReturnType<typeof useFormatting>['formatNumber'], precision = 1): string {
    return `${formatNumber(value * 100, { maximumFractionDigits: precision })}%`;
}

function formatPercentMaybe(metric: string, value: number): string {
    return metric.includes('rate') || metric === 'mape' || metric === 'error_rate'
        ? `${(value * 100).toFixed(1)}%`
        : value.toFixed(2);
}

function buildDefaultForm(): MetricsFormValues {
    const now = new Date();
    const from = new Date(now);
    from.setDate(now.getDate() - DEFAULT_LOOKBACK_DAYS);

    return {
        feature: 'forecast',
        from: from.toISOString().slice(0, 10),
        to: '',
    };
}

function toIsoDate(value?: string, endOfDay = false): string | undefined {
    if (!value) {
        return undefined;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return undefined;
    }

    if (endOfDay) {
        date.setHours(23, 59, 59, 999);
    }

    return date.toISOString();
}
