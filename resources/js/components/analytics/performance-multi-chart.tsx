import { useEffect, useMemo, useState } from 'react';
import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { NameType, ValueType } from 'recharts/types/component/DefaultTooltipContent';

import type { SupplierPerformanceMetricSeries } from '@/hooks/api/analytics/use-analytics';
import { cn } from '@/lib/utils';
import { Skeleton } from '@/components/ui/skeleton';

const DEFAULT_COLORS = ['#2563eb', '#16a34a', '#f97316', '#a855f7', '#0ea5e9', '#6366f1'];

export interface PerformanceMultiChartProps {
    series: SupplierPerformanceMetricSeries[];
    /** Convert ISO date strings into human labels. */
    labelFormatter?: (date: string) => string;
    /** Format tooltip values. */
    valueFormatter?: (value: number, metricKey: string) => string;
    /** Pre-hidden metric keys. */
    initialHiddenKeys?: string[];
    /** Display height. */
    height?: number;
    isLoading?: boolean;
    emptyMessage?: string;
    className?: string;
}

interface ChartBucket {
    raw: string;
    label: string;
    values: Record<string, number>;
}

interface MetricConfig {
    key: string;
    label: string;
    color: string;
}

type ChartSeriesPoint = Record<string, number> & { label: string };

export function PerformanceMultiChart({
    series,
    labelFormatter,
    valueFormatter,
    initialHiddenKeys,
    height = 320,
    isLoading,
    emptyMessage = 'No performance snapshots available.',
    className,
}: PerformanceMultiChartProps) {
    const [hiddenKeys, setHiddenKeys] = useState<Set<string>>(() => new Set(initialHiddenKeys));

    useEffect(() => {
        setHiddenKeys(new Set(initialHiddenKeys));
    }, [initialHiddenKeys]);

    const metricConfigs = useMemo(() => buildMetricConfigs(series), [series]);
    const dataPoints = useMemo(() => buildChartBuckets(series, labelFormatter), [series, labelFormatter]);
    const visibleMetrics = metricConfigs.filter((item) => !hiddenKeys.has(item.key));

    const toggleKey = (key: string) => {
        setHiddenKeys((prev) => {
            const next = new Set(prev);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            return next;
        });
    };

    return (
        <div className={cn('space-y-3', className)}>
            <div className="flex flex-wrap gap-2">
                {metricConfigs.length === 0 ? (
                    <p className="text-xs text-muted-foreground">No metric series available.</p>
                ) : (
                    metricConfigs.map((config) => (
                        <button
                            key={config.key}
                            type="button"
                            onClick={() => toggleKey(config.key)}
                            className={cn(
                                'flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                hiddenKeys.has(config.key)
                                    ? 'border-dashed border-muted-foreground/40 text-muted-foreground'
                                    : 'border-transparent bg-muted text-foreground',
                            )}
                        >
                            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: config.color }} />
                            {config.label}
                        </button>
                    ))
                )}
            </div>
            {isLoading ? (
                <Skeleton className="h-[220px] w-full" />
            ) : dataPoints.length === 0 || visibleMetrics.length === 0 ? (
                <div className="flex h-[200px] items-center justify-center rounded-md border border-dashed border-muted-foreground/40 bg-muted/20 text-sm text-muted-foreground">
                    {emptyMessage}
                </div>
            ) : (
                <div style={{ height }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={dataPoints} margin={{ top: 12, right: 12, left: 12, bottom: 8 }}>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                            <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                            <YAxis tick={{ fontSize: 12 }} />
                            <Tooltip content={<PerformanceTooltip valueFormatter={valueFormatter} />} />
                            <Legend />
                            {visibleMetrics.map((metric) => (
                                <Line
                                    key={metric.key}
                                    type="monotone"
                                    dataKey={metric.key}
                                    name={metric.label}
                                    stroke={metric.color}
                                    strokeWidth={2}
                                    dot={false}
                                    isAnimationActive={false}
                                />
                            ))}
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}

function buildMetricConfigs(series: SupplierPerformanceMetricSeries[]): MetricConfig[] {
    return series.map((metric, index) => ({
        key: metric.metricName,
        label: metric.label ?? metric.metricName,
        color: DEFAULT_COLORS[index % DEFAULT_COLORS.length],
    }));
}

function buildChartBuckets(
    series: SupplierPerformanceMetricSeries[],
    labelFormatter?: (date: string) => string,
): ChartSeriesPoint[] {
    const buckets = new Map<string, ChartBucket>();

    series.forEach((metric) => {
        metric.data.forEach((point) => {
            const label = labelFormatter ? labelFormatter(point.date) : point.date;
            const existing = buckets.get(point.date) ?? { raw: point.date, label, values: {} };
            existing.values[metric.metricName] = point.value;
            buckets.set(point.date, existing);
        });
    });

    return Array.from(buckets.values())
        .sort((a, b) => a.raw.localeCompare(b.raw))
        .map((bucket) => ({ label: bucket.label, ...bucket.values } as ChartSeriesPoint));
}

interface PerformanceTooltipProps {
    active?: boolean;
    payload?: Array<{ value?: ValueType; dataKey?: string | number; color?: string; name?: NameType }>;
    label?: NameType;
    valueFormatter?: (value: number, metricKey: string) => string;
}

function PerformanceTooltip({ active, payload, label, valueFormatter }: PerformanceTooltipProps) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    return (
        <div className="min-w-[160px] rounded-md border bg-background/95 p-3 text-xs shadow-lg">
            <p className="mb-2 font-medium text-muted-foreground">{label}</p>
            <div className="space-y-1">
                {payload.map((item) => {
                    if (!item || typeof item.value !== 'number') {
                        return null;
                    }
                    const key = String(item.dataKey);
                    const formatted = valueFormatter ? valueFormatter(Number(item.value), key) : Number(item.value).toLocaleString();

                    return (
                        <div key={key} className="flex items-center justify-between gap-4">
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <span className="h-2 w-2 rounded-full" style={{ backgroundColor: item.color ?? '#2563eb' }} />
                                {item.name ?? key}
                            </span>
                            <span className="font-semibold text-foreground">{formatted}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
