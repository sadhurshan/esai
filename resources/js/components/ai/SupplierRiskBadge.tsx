import { AlertTriangle, Shield, ShieldCheck } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';

import { errorToast } from '@/components/toasts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useFormatting } from '@/contexts/formatting-context';
import { ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';
import { getSupplierRisk, type SupplierRiskPayload } from '@/services/ai';

const BADGE_STYLES: Record<RiskLevel, string> = {
    low: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-200',
    medium: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-200',
    high: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-200',
    unknown: 'border-muted-foreground/30 bg-muted text-muted-foreground',
};

const RISK_COPY: Record<RiskLevel, string> = {
    low: 'Low risk',
    medium: 'Medium risk',
    high: 'High risk',
    unknown: 'Risk insight',
};

type RiskLevel = 'low' | 'medium' | 'high' | 'unknown';

export interface SupplierRiskInsight extends Record<string, unknown> {
    risk_score?: number | null;
    risk_category?: string | null;
    last_refreshed_at?: string | null;
    last_updated_at?: string | null;
    updated_at?: string | null;
    generated_at?: string | null;
    explanation?: string | string[] | null;
    summary?: string | null;
    badges?: string[] | null;
    mitigation_tips?: string[] | null;
}

export interface SupplierRiskBadgeProps {
    supplierId?: number | null;
    supplier: SupplierRiskPayload['supplier'];
    entityType?: string | null;
    entityId?: number | null;
    className?: string;
    disabled?: boolean;
    label?: string;
    autoLoad?: boolean;
    prefetchOnHover?: boolean;
}

export function SupplierRiskBadge({
    supplierId,
    supplier,
    entityType = 'supplier',
    entityId,
    className,
    disabled = false,
    label = 'Supplier risk',
    autoLoad = false,
    prefetchOnHover = true,
}: SupplierRiskBadgeProps) {
    const { formatNumber, formatDate } = useFormatting();
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [hasFetched, setHasFetched] = useState(false);
    const [isUnavailable, setIsUnavailable] = useState(false);
    const [insight, setInsight] = useState<SupplierRiskInsight | null>(null);

    const riskLevel = useMemo<RiskLevel>(
        () => normalizeRiskLevel(insight?.risk_category),
        [insight?.risk_category],
    );
    const explanationItems = useMemo(
        () => normalizeExplanation(insight?.explanation),
        [insight?.explanation],
    );
    const lastUpdated = useMemo(() => resolveTimestamp(insight), [insight]);

    const handleFetch = useCallback(
        async (options?: { force?: boolean; silent?: boolean }) => {
            if (disabled) {
                return;
            }

            if (!options?.force && (isUnavailable || isLoading || hasFetched)) {
                return;
            }

            setIsLoading(true);
            setIsUnavailable(false);

            try {
                const response = await getSupplierRisk<SupplierRiskInsight>({
                    supplier,
                    entity_type: entityType ?? 'supplier',
                    entity_id: entityId ?? supplierId ?? undefined,
                });

                const data = response.data ?? null;

                if (!data) {
                    throw new ApiError('AI returned an empty risk insight.');
                }

                setInsight(data);
                setHasFetched(true);
            } catch (error) {
                setInsight(null);
                setIsUnavailable(true);

                if (!options?.silent) {
                    const message =
                        error instanceof ApiError
                            ? error.message
                            : 'Unexpected error occurred.';
                    errorToast('Supplier risk unavailable', message);
                }
            } finally {
                setIsLoading(false);
            }
        },
        [
            disabled,
            entityId,
            entityType,
            hasFetched,
            isLoading,
            isUnavailable,
            supplier,
            supplierId,
        ],
    );

    useEffect(() => {
        if (autoLoad && !hasFetched && !disabled) {
            void handleFetch({ silent: true });
        }
    }, [autoLoad, disabled, handleFetch, hasFetched]);

    const handleBadgeClick = () => {
        if (disabled) {
            return;
        }

        setIsDrawerOpen(true);

        if (!insight || isUnavailable) {
            void handleFetch({ force: true });
        }
    };

    const handleHoverPrefetch = () => {
        if (
            !prefetchOnHover ||
            disabled ||
            hasFetched ||
            isLoading ||
            isUnavailable
        ) {
            return;
        }

        void handleFetch({ silent: true });
    };

    const badgeClasses = cn(
        'cursor-pointer transition-colors select-none',
        BADGE_STYLES[riskLevel],
        className,
        {
            'pointer-events-none opacity-60': disabled,
        },
    );

    return (
        <>
            <Badge
                asChild
                variant="outline"
                className={badgeClasses}
                aria-live="polite"
                aria-busy={isLoading && !insight}
            >
                <button
                    type="button"
                    onClick={handleBadgeClick}
                    onMouseEnter={handleHoverPrefetch}
                    onFocus={handleHoverPrefetch}
                    disabled={disabled}
                    className="inline-flex items-center gap-2 outline-none"
                >
                    {renderBadgeIcon({
                        disabled,
                        isUnavailable,
                        isLoading,
                        riskLevel,
                    })}
                    <span className="text-xs font-medium whitespace-nowrap">
                        {renderBadgeLabel({
                            disabled,
                            isUnavailable,
                            isLoading,
                            hasInsight: Boolean(insight),
                            riskLevel,
                            label,
                        })}
                    </span>
                </button>
            </Badge>

            <Sheet open={isDrawerOpen} onOpenChange={setIsDrawerOpen}>
                <SheetContent side="right" className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>{label}</SheetTitle>
                        <SheetDescription>
                            {insight?.summary ??
                                'AI-generated assessment of supplier delivery risk. Review before making sourcing decisions.'}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-6 overflow-y-auto px-1">
                        {renderDrawerBody({
                            disabled,
                            insight,
                            riskLevel,
                            isLoading,
                            isUnavailable,
                            formatNumber,
                            formatDate,
                            explanationItems,
                            lastUpdated,
                        })}
                    </div>

                    <SheetFooter>
                        <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-muted-foreground">
                                Last updated{' '}
                                {formatDate(lastUpdated, { fallback: '—' })}
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleFetch({ force: true })}
                                disabled={disabled || isLoading}
                            >
                                {isLoading ? 'Refreshing…' : 'Refresh insight'}
                            </Button>
                        </div>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </>
    );
}

interface BadgeLabelOptions {
    disabled: boolean;
    isUnavailable: boolean;
    isLoading: boolean;
    hasInsight: boolean;
    riskLevel: RiskLevel;
    label: string;
}

function renderBadgeLabel({
    disabled,
    isUnavailable,
    isLoading,
    hasInsight,
    riskLevel,
    label,
}: BadgeLabelOptions): string {
    if (disabled) {
        return `${label} locked`;
    }

    if (isUnavailable) {
        return 'Hint unavailable';
    }

    if (isLoading && !hasInsight) {
        return 'Analyzing…';
    }

    if (hasInsight) {
        return RISK_COPY[riskLevel];
    }

    return label;
}

interface BadgeIconOptions {
    disabled: boolean;
    isUnavailable: boolean;
    isLoading: boolean;
    riskLevel: RiskLevel;
}

function renderBadgeIcon({
    disabled,
    isUnavailable,
    isLoading,
    riskLevel,
}: BadgeIconOptions) {
    if (disabled || isUnavailable) {
        return (
            <Shield className="h-3.5 w-3.5 text-muted-foreground" aria-hidden />
        );
    }

    if (isLoading) {
        return <Spinner className="h-3.5 w-3.5" aria-hidden />;
    }

    if (riskLevel === 'high') {
        return <AlertTriangle className="h-3.5 w-3.5" aria-hidden />;
    }

    return <ShieldCheck className="h-3.5 w-3.5" aria-hidden />;
}

interface DrawerBodyOptions {
    disabled: boolean;
    insight: SupplierRiskInsight | null;
    riskLevel: RiskLevel;
    isLoading: boolean;
    isUnavailable: boolean;
    explanationItems: string[];
    lastUpdated: string | null;
    formatNumber: ReturnType<typeof useFormatting>['formatNumber'];
    formatDate: ReturnType<typeof useFormatting>['formatDate'];
}

function renderDrawerBody({
    disabled,
    insight,
    riskLevel,
    isLoading,
    isUnavailable,
    explanationItems,
    lastUpdated,
    formatNumber,
    formatDate,
}: DrawerBodyOptions) {
    if (disabled) {
        return (
            <p className="text-sm text-muted-foreground">
                You do not have access to supplier risk insights.
            </p>
        );
    }

    if (isLoading && !insight) {
        return (
            <div className="flex h-32 flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                <Spinner className="h-5 w-5" />
                <span>Generating insight…</span>
            </div>
        );
    }

    if (isUnavailable) {
        return (
            <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                AI risk hint unavailable right now. Please try refreshing in a
                moment.
            </div>
        );
    }

    if (!insight) {
        return (
            <div className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                No AI insight yet. Generate one by refreshing the hint.
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="rounded-lg border bg-card/40 p-5 shadow-sm">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Risk score
                </p>
                <p className="mt-2 text-4xl font-semibold text-foreground">
                    {formatNumber(insight.risk_score ?? null, {
                        maximumFractionDigits: 1,
                    })}
                </p>
                <p className="mt-2 text-sm text-muted-foreground">
                    {RISK_COPY[riskLevel]} • Updated{' '}
                    {formatDate(lastUpdated, { fallback: '—' })}
                </p>
            </div>

            {Array.isArray(insight.badges) && insight.badges.length > 0 ? (
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        Signals
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {insight.badges.map((badge) => (
                            <Badge
                                key={badge}
                                variant="outline"
                                className="text-[11px]"
                            >
                                {badge}
                            </Badge>
                        ))}
                    </div>
                </div>
            ) : null}

            {explanationItems.length > 0 && (
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        Why this matters
                    </p>
                    <ul className="mt-2 list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                        {explanationItems.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}

            {Array.isArray(insight.mitigation_tips) &&
            insight.mitigation_tips.length > 0 ? (
                <div>
                    <p className="text-sm font-semibold text-foreground">
                        Mitigation tips
                    </p>
                    <ul className="mt-2 list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                        {insight.mitigation_tips.map((tip) => (
                            <li key={tip}>{tip}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}

function normalizeRiskLevel(value?: string | null): RiskLevel {
    if (!value) {
        return 'unknown';
    }

    const normalized = value.toLowerCase();

    if (normalized.includes('high')) {
        return 'high';
    }

    if (normalized.includes('medium') || normalized.includes('moderate')) {
        return 'medium';
    }

    if (normalized.includes('low')) {
        return 'low';
    }

    return 'unknown';
}

function normalizeExplanation(value?: string | string[] | null): string[] {
    if (!value) {
        return [];
    }

    const raw = Array.isArray(value) ? value : value.split(/\n|\r/);

    return raw
        .map((item) => (typeof item === 'string' ? item.trim() : ''))
        .filter((item) => item.length > 0);
}

function resolveTimestamp(value?: SupplierRiskInsight | null): string | null {
    if (!value) {
        return null;
    }

    return (
        value.last_refreshed_at ??
        value.last_updated_at ??
        value.updated_at ??
        (typeof value.generated_at === 'string' ? value.generated_at : null) ??
        null
    );
}
