import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { SalesOrderStatus } from '@/types/orders';

interface OrderStatusBadgeProps {
    status?: SalesOrderStatus | string | null;
    className?: string;
}

const STATUS_MAP: Record<
    string,
    {
        label: string;
        className?: string;
        variant: 'default' | 'secondary' | 'outline' | 'destructive';
    }
> = {
    draft: { label: 'Draft', variant: 'secondary' },
    pending_ack: {
        label: 'Awaiting Ack',
        variant: 'outline',
        className: 'border-amber-500 text-amber-600',
    },
    accepted: {
        label: 'Accepted',
        variant: 'default',
        className: 'bg-blue-600 text-white',
    },
    partially_fulfilled: {
        label: 'Partially Fulfilled',
        variant: 'default',
        className: 'bg-indigo-600 text-white',
    },
    fulfilled: {
        label: 'Fulfilled',
        variant: 'default',
        className: 'bg-emerald-600 text-white',
    },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

export function OrderStatusBadge({ status, className }: OrderStatusBadgeProps) {
    const normalized = (status ?? 'unknown').toString().toLowerCase();
    const preset = STATUS_MAP[normalized] ?? {
        label: status ?? 'Unknown',
        variant: 'outline' as const,
    };

    return (
        <Badge
            variant={preset.variant}
            className={cn(
                'tracking-wide uppercase',
                preset.className,
                className,
            )}
        >
            {preset.label}
        </Badge>
    );
}
