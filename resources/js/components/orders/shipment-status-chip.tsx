import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ShipmentStatus } from '@/types/orders';

interface ShipmentStatusChipProps {
    status?: ShipmentStatus | string | null;
    className?: string;
}

const STATUS_PRESETS: Record<
    string,
    {
        label: string;
        className?: string;
        variant: 'outline' | 'default' | 'secondary' | 'destructive';
    }
> = {
    pending: { label: 'Pending', variant: 'secondary' },
    in_transit: {
        label: 'In Transit',
        variant: 'default',
        className: 'bg-sky-600 text-white',
    },
    delivered: {
        label: 'Delivered',
        variant: 'default',
        className: 'bg-emerald-600 text-white',
    },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

export function ShipmentStatusChip({
    status,
    className,
}: ShipmentStatusChipProps) {
    const normalized = (status ?? 'unknown').toString().toLowerCase();
    const preset = STATUS_PRESETS[normalized] ?? {
        label: status ?? 'Unknown',
        variant: 'outline' as const,
    };

    return (
        <Badge
            variant={preset.variant}
            className={cn('text-xs font-medium', preset.className, className)}
        >
            {preset.label}
        </Badge>
    );
}
