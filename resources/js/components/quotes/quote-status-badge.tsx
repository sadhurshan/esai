import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { QuoteStatusEnum } from '@/sdk';

interface QuoteStatusBadgeProps {
    status?: QuoteStatusEnum | string | null;
    className?: string;
}

const STATUS_PRESETS: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'outline' | 'destructive';
        className?: string;
    }
> = {
    draft: { label: 'Draft', variant: 'secondary' },
    submitted: { label: 'Submitted', variant: 'default' },
    awarded: {
        label: 'Awarded',
        variant: 'default',
        className: 'bg-emerald-600 text-white',
    },
    withdrawn: { label: 'Withdrawn', variant: 'destructive' },
    expired: {
        label: 'Expired',
        variant: 'outline',
        className: 'border-amber-500 text-amber-600',
    },
    rejected: {
        label: 'Rejected',
        variant: 'outline',
        className: 'border-destructive/60 text-destructive',
    },
    lost: {
        label: 'Lost',
        variant: 'outline',
        className: 'text-muted-foreground',
    },
};

export function QuoteStatusBadge({ status, className }: QuoteStatusBadgeProps) {
    const normalized = (status ?? 'unknown').toString().toLowerCase();
    const presentation = STATUS_PRESETS[normalized] ?? {
        label: status ?? 'Unknown',
        variant: 'outline' as const,
    };

    return (
        <Badge
            variant={presentation.variant}
            className={cn(
                'tracking-wide uppercase',
                presentation.className,
                className,
            )}
        >
            {presentation.label}
        </Badge>
    );
}
