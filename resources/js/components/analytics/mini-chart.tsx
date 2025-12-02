import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { NameType, ValueType } from 'recharts/types/component/DefaultTooltipContent';

const DEFAULT_COLORS = ['#2563eb', '#16a34a', '#f97316', '#a855f7', '#0ea5e9'];

export interface MiniChartSeriesConfig {
    key: string;
    label: string;
    color?: string;
    type?: 'line' | 'bar';
    stackId?: string;
}

interface MiniChartProps {
    title: string;
    description?: string;
    data: Array<Record<string, string | number | null>>;
    series: MiniChartSeriesConfig[];
    categoryKey?: string;
    isLoading?: boolean;
    emptyMessage?: string;
    height?: number;
    valueFormatter?: (value: number, seriesKey: string) => string;
}

export function MiniChart({
    title,
    description,
    data,
    series,
    categoryKey = 'label',
    isLoading,
    emptyMessage = 'No data available yet.',
    height = 240,
    valueFormatter,
}: MiniChartProps) {
    const chartType = series.some((item) => item.type === 'bar') ? 'bar' : 'line';
    const safeData = Array.isArray(data) ? data : [];

    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle className="text-base font-semibold">{title}</CardTitle>
                {description ? <CardDescription className="text-sm text-muted-foreground">{description}</CardDescription> : null}
            </CardHeader>
            <CardContent className="h-full">
                {isLoading ? (
                    <div style={{ height }} className="w-full">
                        <Skeleton className="h-full w-full" />
                    </div>
                ) : safeData.length === 0 ? (
                    <div
                        className="flex flex-col items-center justify-center text-sm text-muted-foreground"
                        style={{ height }}
                    >
                        {emptyMessage}
                    </div>
                ) : (
                    <div style={{ height }}>
                        <ResponsiveContainer width="100%" height="100%">
                            {chartType === 'line' ? (
                                <LineChart data={safeData} margin={{ top: 8, right: 8, left: 8, bottom: 8 }}>
                                    <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                    <XAxis dataKey={categoryKey} tick={{ fontSize: 12 }} />
                                    <YAxis tick={{ fontSize: 12 }} />
                                    <Tooltip
                                        content={<MiniChartTooltip valueFormatter={valueFormatter} />}
                                        cursor={{ stroke: '#cbd5f5' }}
                                    />
                                    <Legend />
                                    {series.map((s, index) => (
                                        <Line
                                            key={s.key}
                                            type="monotone"
                                            dataKey={s.key}
                                            name={s.label}
                                            stroke={s.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length]}
                                            strokeWidth={2}
                                            dot={false}
                                            isAnimationActive={false}
                                        />
                                    ))}
                                </LineChart>
                            ) : (
                                <BarChart data={safeData} margin={{ top: 8, right: 8, left: 8, bottom: 8 }}>
                                    <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                    <XAxis dataKey={categoryKey} tick={{ fontSize: 12 }} />
                                    <YAxis tick={{ fontSize: 12 }} />
                                    <Tooltip content={<MiniChartTooltip valueFormatter={valueFormatter} />} />
                                    <Legend />
                                    {series.map((s, index) => (
                                        <Bar
                                            key={s.key}
                                            dataKey={s.key}
                                            name={s.label}
                                            stackId={s.stackId}
                                            fill={s.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length]}
                                            radius={4}
                                        />
                                    ))}
                                </BarChart>
                            )}
                        </ResponsiveContainer>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

interface MiniChartTooltipProps {
    active?: boolean;
    label?: NameType;
    payload?: Array<{
        color?: string;
        dataKey?: string | number;
        name?: NameType;
        value?: ValueType;
    }>;
    valueFormatter?: (value: number, seriesKey: string) => string;
}

function MiniChartTooltip({ active, payload, label, valueFormatter }: MiniChartTooltipProps) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    return (
        <div className="min-w-[160px] rounded-md border bg-background/95 p-3 text-xs shadow-lg">
            <p className="mb-2 font-medium text-muted-foreground">{label}</p>
            <div className="space-y-1">
                {payload.map((item) => {
                    if (!item || item.value === undefined) {
                        return null;
                    }

                    const color = item.color ?? '#2563eb';
                    const seriesKey = typeof item.dataKey === 'string' ? item.dataKey : String(item.dataKey ?? 'value');
                    const formattedValue = valueFormatter
                        ? valueFormatter(Number(item.value), seriesKey)
                        : Number(item.value).toLocaleString();

                    return (
                        <div key={`${seriesKey}-${item.name}`} className="flex items-center justify-between gap-4">
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <span className="h-2 w-2 rounded-full" style={{ backgroundColor: color }} />
                                {item.name ?? seriesKey}
                            </span>
                            <span className="font-semibold text-foreground">{formattedValue}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
