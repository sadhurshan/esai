import { Badge } from '@/components/ui/badge';
import type { EventDeliveryStatus } from '@/types/notifications';

const STATUS_COPY: Record<
    EventDeliveryStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    pending: { label: 'Pending', variant: 'secondary' },
    success: { label: 'Delivered', variant: 'default' },
    failed: { label: 'Failed', variant: 'destructive' },
    dead_letter: { label: 'Dead letter', variant: 'outline' },
};

export interface DeliveryStatusBadgeProps {
    status: EventDeliveryStatus;
}

export function DeliveryStatusBadge({ status }: DeliveryStatusBadgeProps) {
    const config = STATUS_COPY[status] ?? STATUS_COPY.pending;

    return <Badge variant={config.variant}>{config.label}</Badge>;
}
