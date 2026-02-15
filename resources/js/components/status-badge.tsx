import { Badge } from '@/components/ui/badge';
import {
    COMPANY_STATUS_BADGE_MAP,
    ORDER_STATUS_BADGE_MAP,
    RFQ_STATUS_BADGE_MAP,
} from '@/lib/constants';
import { cn } from '@/lib/utils';

const STATUS_MAP: Record<string, string> = {
    ...RFQ_STATUS_BADGE_MAP,
    ...ORDER_STATUS_BADGE_MAP,
    ...COMPANY_STATUS_BADGE_MAP,
    cancelled_order:
        'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-100',
};

interface StatusBadgeProps {
    status: string;
    className?: string;
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
    const normalized = status.toLowerCase().replace(/\s+/g, '_');
    const variantClasses = STATUS_MAP[normalized] ?? STATUS_MAP.draft;

    return (
        <Badge
            variant="secondary"
            className={cn(
                'tracking-wide capitalize',
                variantClasses,
                className,
            )}
        >
            {status.replace(/_/g, ' ')}
        </Badge>
    );
}
