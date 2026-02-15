import { useMemo } from 'react';
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
import type {
    NameType,
    ValueType,
} from 'recharts/types/component/DefaultTooltipContent';

import { Skeleton } from '@/components/ui/skeleton';
import type { ForecastReportSeries } from '@/hooks/api/analytics/use-analytics';
import { cn } from '@/lib/utils';

export interface ForecastLineChartProps {
    /** List of series returned by the analytics forecast API. */
    series: ForecastReportSeries[];
    /** Optional part identifier to isolate a single series. */
    focusPartId?: number | null;
    /** Custom label formatter for x-axis values. */
    labelFormatter?: (date: string) => string;
    /** Tooltip formatter for values. */
    valueFormatter?: (value: number, key: 'actual' | 'forecast') => string;
    /** Height of the chart viewport. */
    height?: number;
    /** Render skeleton state when loading. */
    isLoading?: boolean;
    /** Message displayed when there is no data. */
    emptyMessage?: string;
    className?: string;
}

interface AggregatedPoint {
    label: string;
    raw: string;
    actual: number;
    forecast: number;
}

const DEFAULT_COLORS = {
    actual: '#0ea5e9',
    forecast: '#a855f7',
};

export function ForecastLineChart({
    series,
    focusPartId,
    labelFormatter,
    valueFormatter,
    height = 280,
    isLoading,
    emptyMessage = 'No forecast snapshots available.',
    className,
}: ForecastLineChartProps) {
    const chartData = useMemo(() => {
        return buildChartData(
            series ?? [],
            focusPartId ?? null,
            labelFormatter,
        );
    }, [series, focusPartId, labelFormatter]);

    return (
        <div className={cn('w-full', className)}>
            {isLoading ? (
                <Skeleton className="h-[220px] w-full" />
            ) : chartData.length === 0 ? (
                <div className="flex h-[180px] items-center justify-center rounded-md border border-dashed border-muted-foreground/40 bg-muted/20 text-sm text-muted-foreground">
                    {emptyMessage}
                </div>
            ) : (
                <div style={{ height }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <LineChart
                            data={chartData}
                            margin={{ top: 10, right: 12, left: 12, bottom: 8 }}
                        >
                            <CartesianGrid
                                strokeDasharray="3 3"
                                className="stroke-muted"
                            />
                            <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                            <YAxis tick={{ fontSize: 12 }} />
                            <Tooltip
                                content={
                                    <ForecastTooltip
                                        valueFormatter={valueFormatter}
                                    />
                                }
                            />
                            <Legend />
                            <Line
                                type="monotone"
                                dataKey="actual"
                                name="Actual"
                                stroke={DEFAULT_COLORS.actual}
                                strokeWidth={2}
                                dot={false}
                                isAnimationActive={false}
                            />
                            <Line
                                type="monotone"
                                dataKey="forecast"
                                name="Forecast"
                                stroke={DEFAULT_COLORS.forecast}
                                strokeWidth={2}
                                dot={false}
                                strokeDasharray="5 3"
                                isAnimationActive={false}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}

function buildChartData(
    series: ForecastReportSeries[],
    focusPartId: number | null,
    labelFormatter?: (date: string) => string,
): AggregatedPoint[] {
    const buckets = new Map<string, AggregatedPoint>();
    const filteredSeries = focusPartId
        ? series.filter((entry) => entry.partId === focusPartId)
        : series;

    filteredSeries.forEach((entry) => {
        entry.data.forEach((point) => {
            const label = labelFormatter
                ? labelFormatter(point.date)
                : point.date;
            const existing = buckets.get(point.date) ?? {
                label,
                raw: point.date,
                actual: 0,
                forecast: 0,
            };
            existing.actual += point.actual;
            existing.forecast += point.forecast;
            buckets.set(point.date, existing);
        });
    });

    return Array.from(buckets.values()).sort((a, b) =>
        a.raw.localeCompare(b.raw),
    );
}

interface ForecastTooltipProps {
    active?: boolean;
    payload?: Array<{
        value?: ValueType;
        dataKey?: string | number;
        color?: string;
        name?: NameType;
    }>;
    label?: NameType;
    valueFormatter?: (value: number, key: 'actual' | 'forecast') => string;
}

function ForecastTooltip({
    active,
    payload,
    label,
    valueFormatter,
}: ForecastTooltipProps) {
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
                    const formatted = valueFormatter
                        ? valueFormatter(
                              Number(item.value),
                              key === 'actual' ? 'actual' : 'forecast',
                          )
                        : Number(item.value).toLocaleString();

                    return (
                        <div
                            key={key}
                            className="flex items-center justify-between gap-4"
                        >
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <span
                                    className="h-2 w-2 rounded-full"
                                    style={{
                                        backgroundColor:
                                            item.color ?? '#2563eb',
                                    }}
                                />
                                {item.name ?? key}
                            </span>
                            <span className="font-semibold text-foreground">
                                {formatted}
                            </span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
