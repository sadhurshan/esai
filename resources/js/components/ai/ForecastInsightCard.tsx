import { useMemo, useState } from 'react';
import { Sparkles } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { errorToast } from '@/components/toasts';
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
import { Spinner } from '@/components/ui/spinner';
import { useFormatting } from '@/contexts/formatting-context';
import { ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';
import { getForecast, type ForecastPayload } from '@/services/ai';

const DEFAULT_HORIZON_DAYS = 30;

export interface ForecastHistoryPoint {
    date: string;
    quantity: number;
}

export interface ForecastInsight extends Record<string, unknown> {
    model?: string | null;
    demand_qty?: number | null;
    avg_daily_demand?: number | null;
    safety_stock?: number | null;
    reorder_point?: number | null;
    order_by_date?: string | null;
    explanation?: string | string[] | null;
}

interface ForecastInsightCardProps {
    partId: number;
    history: ForecastHistoryPoint[];
    horizon?: number;
    disabled?: boolean;
    className?: string;
    onApply?: (insight: ForecastInsight) => void;
}

export function ForecastInsightCard({
    partId,
    history,
    horizon = DEFAULT_HORIZON_DAYS,
    disabled = false,
    className,
    onApply,
}: ForecastInsightCardProps) {
    const safeHorizon = Math.min(90, Math.max(1, Math.round(horizon)));
    const { formatNumber, formatDate } = useFormatting();
    const [insight, setInsight] = useState<ForecastInsight | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    const normalizedHistory = useMemo(() => {
        return history
            .filter((entry) => entry?.date)
            .map<ForecastPayload['history'][number]>((entry) => {
                const numericQuantity = Number(entry.quantity);

                return {
                    date: entry.date,
                    quantity: Number.isFinite(numericQuantity) ? numericQuantity : 0,
                };
            });
    }, [history]);

    const hasHistory = normalizedHistory.length > 0;

    const explanationItems = useMemo(() => {
        if (!insight?.explanation) {
            return [] as string[];
        }

        const raw = Array.isArray(insight.explanation)
            ? insight.explanation
            : [insight.explanation];

        return raw
            .map((item) => (typeof item === 'string' ? item.trim() : ''))
            .filter((item) => item.length > 0);
    }, [insight?.explanation]);

    const historySummary = useMemo(() => {
        if (!hasHistory) {
            return 'Add usage history to unlock AI-powered reorder guidance.';
        }

        const sorted = [...normalizedHistory].sort(
            (a, b) => new Date(a.date).getTime() - new Date(b.date).getTime(),
        );
        const start = formatDate(sorted[0].date);
        const end = formatDate(sorted[sorted.length - 1].date);
        const range = start === end ? end : `${start} → ${end}`;

        return `${normalizedHistory.length} data points • ${range}`;
    }, [formatDate, hasHistory, normalizedHistory]);

    const metricCards = useMemo(() => {
        if (!insight) {
            return [] as Array<{ label: string; value: string; helper?: string }>;
        }

        return [
            {
                label: 'Safety stock',
                value: formatNumber(insight.safety_stock ?? null, { maximumFractionDigits: 0 }),
                helper: 'Buffer to absorb demand spikes.',
            },
            {
                label: 'Reorder point',
                value: formatNumber(insight.reorder_point ?? null, { maximumFractionDigits: 0 }),
                helper: 'Includes safety stock + lead time coverage.',
            },
            {
                label: 'Avg daily demand',
                value: formatNumber(insight.avg_daily_demand ?? null, { maximumFractionDigits: 1 }),
            },
            {
                label: 'Forecast horizon',
                value: `${safeHorizon} days`,
            },
            {
                label: 'Order by date',
                value: formatDate(insight.order_by_date, { fallback: '—' }),
            },
            {
                label: 'Model used',
                value: formatModelName(insight.model),
            },
        ];
    }, [formatDate, formatNumber, insight, safeHorizon]);

    const handleGenerate = async () => {
        if (disabled || isLoading) {
            return;
        }

        if (!hasHistory) {
            errorToast(
                'Demand history required',
                'Provide at least one historical usage point before running a forecast.',
            );
            return;
        }

        setIsLoading(true);

        try {
            const response = await getForecast<ForecastInsight>({
                part_id: partId,
                history: normalizedHistory,
                horizon: safeHorizon,
            });

            const data = response.data ?? null;

            if (!data) {
                throw new ApiError('AI returned an empty forecast. Please retry.');
            }

            setInsight(data);
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unexpected error occurred.';
            errorToast('Unable to generate forecast', message);
        } finally {
            setIsLoading(false);
        }
    };

    const handleApply = () => {
        if (!insight || !onApply) {
            return;
        }

        onApply(insight);
    };

    const renderContent = () => {
        if (isLoading && !insight) {
            return <InsightSkeleton />;
        }

        if (!insight) {
            return (
                <EmptyState
                    title="No forecast yet"
                    description={historySummary}
                    icon={<Sparkles className="size-6" />}
                    className="bg-transparent"
                    ctaLabel={hasHistory ? 'Generate forecast' : undefined}
                    ctaProps={{
                        onClick: handleGenerate,
                        disabled: !hasHistory || disabled,
                    }}
                />
            );
        }

        return (
            <div className="space-y-4">
                <div className="grid gap-4 md:grid-cols-2">
                    {metricCards.map((metric) => (
                        <div
                            key={metric.label}
                            className="rounded-lg border bg-card/40 p-4 shadow-sm"
                        >
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {metric.label}
                            </p>
                            <p className="mt-1 text-xl font-semibold text-foreground">{metric.value}</p>
                            {metric.helper ? (
                                <p className="mt-1 text-xs text-muted-foreground">{metric.helper}</p>
                            ) : null}
                        </div>
                    ))}
                </div>

                {explanationItems.length > 0 && (
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <p className="text-sm font-semibold text-foreground">Model explanation</p>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                            {explanationItems.map((item, index) => (
                                <li key={`${item}-${index}`}>{item}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <p className="text-xs text-muted-foreground">{historySummary}</p>
            </div>
        );
    };

    return (
        <Card className={cn('h-full', className)}>
            <CardHeader className="gap-3">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle>Forecast insight</CardTitle>
                        <CardDescription>
                            Generate safety stock and reorder recommendations directly from recent demand.
                        </CardDescription>
                    </div>
                    <Button
                        onClick={handleGenerate}
                        disabled={!hasHistory || disabled || isLoading}
                        variant="default"
                    >
                        {isLoading ? (
                            <>
                                <Spinner className="mr-2" />
                                Generating…
                            </>
                        ) : (
                            'Generate forecast'
                        )}
                    </Button>
                </div>
            </CardHeader>
            <CardContent>{renderContent()}</CardContent>
            {onApply && (
                <CardFooter className="flex-col items-start gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        Review the insight before applying it to your reorder settings.
                    </p>
                    <Button variant="outline" onClick={handleApply} disabled={!insight || isLoading}>
                        Apply to reorder settings
                    </Button>
                </CardFooter>
            )}
        </Card>
    );
}

function InsightSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="rounded-lg border bg-card/40 p-4">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="mt-3 h-6 w-32" />
                    <Skeleton className="mt-2 h-3 w-28" />
                </div>
            ))}
        </div>
    );
}

function formatModelName(model?: string | null) {
    if (!model) {
        return '—';
    }

    return model
        .split(/[_\s]+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
