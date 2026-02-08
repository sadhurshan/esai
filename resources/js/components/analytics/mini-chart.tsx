import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { NameType, ValueType } from 'recharts/types/component/DefaultTooltipContent';

const DEFAULT_COLORS = ['#2563eb', '#16a34a', '#f97316', '#a855f7', '#0ea5e9'];
const GRID_COLOR = '#e2e8f0';

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
        <Card className="h-full border-border/70 shadow-sm">
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-semibold text-foreground">{title}</CardTitle>
                {description ? (
                    <CardDescription className="text-sm text-muted-foreground">{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className="h-full pt-0">
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
                    <div
                        style={{ height }}
                        className="rounded-md border border-border/60 bg-gradient-to-b from-background to-muted/20 p-2"
                    >
                        <ResponsiveContainer width="100%" height="100%">
                            {chartType === 'line' ? (
                                <LineChart data={safeData} margin={{ top: 12, right: 12, left: 8, bottom: 8 }}>
                                    <CartesianGrid strokeDasharray="4 4" stroke={GRID_COLOR} />
                                    <XAxis
                                        dataKey={categoryKey}
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        tickLine={false}
                                        axisLine={{ stroke: GRID_COLOR }}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        tickLine={false}
                                        axisLine={{ stroke: GRID_COLOR }}
                                    />
                                    <Tooltip
                                        content={<MiniChartTooltip valueFormatter={valueFormatter} />}
                                        cursor={{ stroke: '#cbd5f5', strokeDasharray: '3 3' }}
                                    />
                                    <Legend wrapperStyle={{ fontSize: 12, color: '#64748b' }} />
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
                                <BarChart data={safeData} margin={{ top: 12, right: 12, left: 8, bottom: 8 }}>
                                    <CartesianGrid strokeDasharray="4 4" stroke={GRID_COLOR} />
                                    <XAxis
                                        dataKey={categoryKey}
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        tickLine={false}
                                        axisLine={{ stroke: GRID_COLOR }}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 12, fill: '#64748b' }}
                                        tickLine={false}
                                        axisLine={{ stroke: GRID_COLOR }}
                                    />
                                    <Tooltip content={<MiniChartTooltip valueFormatter={valueFormatter} />} cursor={{ fill: 'rgba(148, 163, 184, 0.12)' }} />
                                    <Legend wrapperStyle={{ fontSize: 12, color: '#64748b' }} />
                                    {series.map((s, index) => (
                                        <Bar
                                            key={s.key}
                                            dataKey={s.key}
                                            name={s.label}
                                            stackId={s.stackId}
                                            fill={s.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length]}
                                            radius={6}
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
        <div className="min-w-[160px] rounded-lg border border-border/70 bg-background/95 p-3 text-xs shadow-lg backdrop-blur">
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
                                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} />
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
