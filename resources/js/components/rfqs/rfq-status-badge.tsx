import { Badge } from '@/components/ui/badge';
import type { RfqStatusEnum } from '@/sdk';

const STATUS_VARIANTS: Record<RfqStatusEnum, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    awaiting: { label: 'Draft', variant: 'secondary' },
    open: { label: 'Open', variant: 'default' },
    closed: { label: 'Closed', variant: 'outline' },
    awarded: { label: 'Awarded', variant: 'default' },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

export interface RfqStatusBadgeProps {
    status: RfqStatusEnum;
}

export function RfqStatusBadge({ status }: RfqStatusBadgeProps) {
    const config = STATUS_VARIANTS[status] ?? { label: status, variant: 'outline' as const };

    return <Badge variant={config.variant}>{config.label}</Badge>;
}
