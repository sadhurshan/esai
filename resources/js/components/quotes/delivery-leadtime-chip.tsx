import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Clock } from 'lucide-react';

interface DeliveryLeadTimeChipProps {
    leadTimeDays?: number | null;
    className?: string;
}

function formatLeadTime(leadTimeDays?: number | null): string {
    if (leadTimeDays === null || leadTimeDays === undefined) {
        return '—';
    }

    if (leadTimeDays === 0) {
        return 'Same day';
    }

    return `${leadTimeDays} day${leadTimeDays === 1 ? '' : 's'}`;
}

export function DeliveryLeadTimeChip({
    leadTimeDays,
    className,
}: DeliveryLeadTimeChipProps) {
    return (
        <Badge
            variant="outline"
            className={cn('gap-1 text-xs font-medium', className)}
        >
            <Clock className="h-3.5 w-3.5 text-muted-foreground" />
            {formatLeadTime(leadTimeDays)}
        </Badge>
    );
}
