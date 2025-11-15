import type { ComponentType } from 'react';

import { AlertTriangle, Info, ShieldAlert } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { MatchDiscrepancy } from '@/types/sourcing';

const severityStyles: Record<NonNullable<MatchDiscrepancy['severity']>, string> = {
    info: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-100',
    warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-400/30 dark:bg-amber-950/40 dark:text-amber-200',
    critical: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-400/30 dark:bg-rose-950/40 dark:text-rose-200',
};

const iconBySeverity: Record<NonNullable<MatchDiscrepancy['severity']>, ComponentType<{ className?: string }>> = {
    info: Info,
    warning: AlertTriangle,
    critical: ShieldAlert,
};

interface DiscrepancyBadgeProps {
    discrepancy: MatchDiscrepancy;
    className?: string;
}

export const DiscrepancyBadge = ({ discrepancy, className }: DiscrepancyBadgeProps) => {
    const severity = discrepancy.severity ?? 'warning';
    const Icon = iconBySeverity[severity];

    return (
        <Badge variant="outline" className={cn('inline-flex items-center gap-1.5 text-xs font-medium', severityStyles[severity], className)}>
            <Icon className="h-3.5 w-3.5" />
            <span>{discrepancy.label}</span>
        </Badge>
    );
};
