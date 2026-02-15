import { Badge } from '@/components/ui/badge';
import type { RfqStatusEnum } from '@/sdk';

type RfqStatusLike = RfqStatusEnum | 'draft';

const STATUS_VARIANTS: Record<
    RfqStatusLike,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    awaiting: { label: 'Draft', variant: 'secondary' },
    draft: { label: 'Draft', variant: 'secondary' },
    open: { label: 'Open', variant: 'default' },
    closed: { label: 'Closed', variant: 'outline' },
    awarded: { label: 'Awarded', variant: 'default' },
    cancelled: { label: 'Cancelled', variant: 'destructive' },
};

export interface RfqStatusBadgeProps {
    status: RfqStatusEnum | 'draft';
}

export function RfqStatusBadge({ status }: RfqStatusBadgeProps) {
    const normalizedStatus: RfqStatusLike =
        status === 'draft' ? 'draft' : status;
    const config = STATUS_VARIANTS[normalizedStatus] ?? {
        label: status,
        variant: 'outline' as const,
    };

    return <Badge variant={config.variant}>{config.label}</Badge>;
}
