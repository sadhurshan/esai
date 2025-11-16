import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { useNumberingSettings } from '@/hooks/api/settings';
import { formatNumberingSample } from '@/lib/numbering';
import { cn } from '@/lib/utils';
import type { NumberingSettings } from '@/types/settings';

const DOC_LABELS: Record<keyof NumberingSettings, string> = {
    rfq: 'RFQ',
    quote: 'Quote',
    po: 'Purchase Order',
    invoice: 'Invoice',
    grn: 'GRN',
    credit: 'Credit Note',
};

const RESET_HINT: Record<string, string> = {
    never: 'Continuous sequence',
    yearly: 'Resets yearly',
};

export interface DocumentNumberPreviewProps {
    docType: keyof NumberingSettings;
    label?: string;
    hint?: string;
    className?: string;
}

export function DocumentNumberPreview({ docType, label, hint = 'Final number is assigned when you save.', className }: DocumentNumberPreviewProps) {
    const numberingQuery = useNumberingSettings();
    const headerLabel = label ?? `Next ${DOC_LABELS[docType]} number`;

    if (numberingQuery.isLoading) {
        return <Skeleton className={cn('h-20 w-full max-w-sm rounded-lg', className)} />;
    }

    if (numberingQuery.isError) {
        return (
            <div className={cn('rounded-lg border border-dashed border-border/70 bg-muted/40 px-4 py-3 text-sm', className)}>
                <p className="text-xs uppercase tracking-wide text-muted-foreground">{headerLabel}</p>
                <p className="text-sm text-muted-foreground">Numbering preview unavailable.</p>
            </div>
        );
    }

    const rule = numberingQuery.data?.[docType];
    const sample = formatNumberingSample(rule, '—');
    const resetCopy = rule?.reset ? RESET_HINT[rule.reset] ?? null : null;

    return (
        <div className={cn('rounded-lg border border-dashed border-border/70 bg-muted/20 px-4 py-3 text-sm', className)}>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{headerLabel}</p>
            <div className="mt-1 flex flex-wrap items-center gap-2">
                <Badge variant="secondary" className="font-mono text-sm">
                    {sample}
                </Badge>
                {resetCopy ? <span className="text-[11px] uppercase tracking-wide text-muted-foreground">{resetCopy}</span> : null}
            </div>
            <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
        </div>
    );
}
