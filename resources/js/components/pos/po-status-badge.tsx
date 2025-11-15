import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STATUS_STYLES: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; className?: string }> = {
    draft: { variant: 'outline', className: 'border-dashed text-muted-foreground' },
    sent: { variant: 'secondary', className: 'bg-sky-100 text-sky-900 dark:bg-sky-500/20 dark:text-sky-100' },
    acknowledged: { variant: 'secondary', className: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100' },
    fulfilled: { variant: 'secondary', className: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100' },
    closed: { variant: 'secondary', className: 'bg-slate-200 text-slate-900 dark:bg-slate-500/30 dark:text-slate-100' },
    cancelled: { variant: 'destructive' },
};

export interface PoStatusBadgeProps {
    status?: string | null;
    className?: string;
}

export function PoStatusBadge({ status, className }: PoStatusBadgeProps) {
    if (!status) {
        return null;
    }

    const key = status.toLowerCase();
    const style = STATUS_STYLES[key] ?? { variant: 'outline', className: 'text-muted-foreground' };

    return (
        <Badge variant={style.variant} className={cn('uppercase tracking-wide', style.className, className)}>
            {status.replace(/_/g, ' ')}
        </Badge>
    );
}
