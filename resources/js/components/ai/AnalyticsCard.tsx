import { useId, useMemo } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type {
    NameType,
    ValueType,
} from 'recharts/types/component/DefaultTooltipContent';
import type { TooltipContentProps } from 'recharts/types/component/Tooltip';

import type {
    AiAnalyticsChartDatum,
    AiAnalyticsCitation,
} from '@/types/ai-analytics';

interface AnalyticsCardProps {
    title: string;
    chartData: AiAnalyticsChartDatum[];
    summary?: string | null;
    citations?: AiAnalyticsCitation[];
    valueFormatter?: (value: number) => string;
}

export function AnalyticsCard({
    title,
    chartData,
    summary,
    citations,
    valueFormatter,
}: AnalyticsCardProps) {
    const gradientId = useId();
    const safeData = useMemo(() => {
        return Array.isArray(chartData)
            ? chartData
                  .map((datum) => {
                      const label =
                          typeof datum.label === 'string'
                              ? datum.label.trim()
                              : '';
                      const value =
                          typeof datum.value === 'number' &&
                          Number.isFinite(datum.value)
                              ? datum.value
                              : null;
                      if (!label || value === null) {
                          return null;
                      }
                      return { label, value };
                  })
                  .filter((datum): datum is { label: string; value: number } =>
                      Boolean(datum),
                  )
            : [];
    }, [chartData]);

    const safeCitations = useMemo(() => {
        return Array.isArray(citations)
            ? citations
                  .map((citation, index) => {
                      if (!citation || typeof citation !== 'object') {
                          return null;
                      }
                      const label =
                          typeof citation.label === 'string'
                              ? citation.label.trim()
                              : '';
                      if (!label) {
                          return null;
                      }
                      const url =
                          typeof citation.url === 'string' &&
                          citation.url.trim().length
                              ? citation.url
                              : null;
                      const source =
                          typeof citation.source === 'string' &&
                          citation.source.trim().length
                              ? citation.source
                              : null;
                      return {
                          id: citation.id ?? `${label}-${index}`,
                          label,
                          url,
                          source,
                      };
                  })
                  .filter(
                      (
                          citation,
                      ): citation is {
                          id: string | number;
                          label: string;
                          url: string | null;
                          source: string | null;
                      } => Boolean(citation),
                  )
            : [];
    }, [citations]);

    return (
        <div className="copilot-analytics-card">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold text-white">{title}</p>
                    <p className="text-xs tracking-wide text-white/60 uppercase">
                        Analytics insight
                    </p>
                </div>
                <span className="copilot-analytics-card__badge">Forecast</span>
            </div>

            <div className="copilot-analytics-card__chart mt-4">
                {safeData.length > 0 ? (
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart
                            data={safeData}
                            margin={{ top: 10, right: 8, left: 8, bottom: 4 }}
                        >
                            <defs>
                                <linearGradient
                                    id={gradientId}
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stopColor="#38bdf8"
                                        stopOpacity={0.9}
                                    />
                                    <stop
                                        offset="100%"
                                        stopColor="#6366f1"
                                        stopOpacity={0.8}
                                    />
                                </linearGradient>
                            </defs>
                            <CartesianGrid
                                vertical={false}
                                stroke="rgba(255,255,255,0.08)"
                                strokeDasharray="3 3"
                            />
                            <XAxis
                                dataKey="label"
                                axisLine={false}
                                tickLine={false}
                                tick={{
                                    fill: 'rgba(226,232,240,0.8)',
                                    fontSize: 12,
                                }}
                            />
                            <YAxis
                                axisLine={false}
                                tickLine={false}
                                tick={{
                                    fill: 'rgba(203,213,225,0.8)',
                                    fontSize: 12,
                                }}
                                tickFormatter={(value: number) =>
                                    formatAnalyticsValue(value, valueFormatter)
                                }
                            />
                            <Tooltip
                                cursor={{ fill: 'rgba(148, 163, 184, 0.15)' }}
                                content={(props) => (
                                    <AnalyticsChartTooltip
                                        {...props}
                                        valueFormatter={valueFormatter}
                                    />
                                )}
                            />
                            <Bar
                                dataKey="value"
                                fill={`url(#${gradientId})`}
                                radius={[8, 8, 0, 0]}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                ) : (
                    <div className="copilot-analytics-card__empty">
                        No chart data provided.
                    </div>
                )}
            </div>

            {summary ? (
                <p className="copilot-analytics-card__summary">{summary}</p>
            ) : null}

            {safeCitations.length > 0 ? (
                <div className="copilot-analytics-card__citations">
                    <p className="text-xs tracking-wide text-white/50 uppercase">
                        Grounded in
                    </p>
                    <ul className="mt-2 space-y-1 text-sm">
                        {safeCitations.map((citation) => (
                            <li
                                key={citation.id}
                                className="flex flex-wrap items-center gap-2 text-white/80"
                            >
                                {citation.url ? (
                                    <a
                                        href={citation.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="underline-offset-2 hover:underline"
                                    >
                                        {citation.label}
                                    </a>
                                ) : (
                                    <span>{citation.label}</span>
                                )}
                                {citation.source ? (
                                    <span className="text-xs tracking-wide text-white/50 uppercase">
                                        {citation.source}
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}

interface AnalyticsChartTooltipProps extends TooltipContentProps<
    ValueType,
    NameType
> {
    valueFormatter?: (value: number) => string;
}

function AnalyticsChartTooltip({
    active,
    payload,
    label,
    valueFormatter,
}: AnalyticsChartTooltipProps) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    const value =
        typeof payload[0].value === 'number'
            ? payload[0].value
            : Number(payload[0].value ?? 0);
    if (!Number.isFinite(value)) {
        return null;
    }

    return (
        <div className="rounded-xl border border-white/10 bg-slate-950/90 px-3 py-2 text-xs text-white shadow-xl">
            <p className="font-semibold text-white">{label}</p>
            <p className="text-white/80">
                {formatAnalyticsValue(value, valueFormatter)}
            </p>
        </div>
    );
}

function formatAnalyticsValue(
    value: number,
    formatter?: (value: number) => string,
): string {
    if (!Number.isFinite(value)) {
        return '—';
    }

    if (formatter) {
        return formatter(value);
    }

    try {
        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return value.toString();
    }
}
