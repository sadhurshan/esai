import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { PurchaseOrderDelivery } from '@/types/sourcing';
import { cn } from '@/lib/utils';
import { formatDistanceToNow, format } from 'date-fns';
import { Fragment } from 'react';

type AckState = 'draft' | 'sent' | 'acknowledged' | 'declined' | undefined;

const STATUS_LABELS: Record<NonNullable<AckState>, string> = {
    draft: 'Draft',
    sent: 'Sent to supplier',
    acknowledged: 'Acknowledged',
    declined: 'Declined',
};

const STATUS_VARIANTS: Record<NonNullable<AckState>, 'outline' | 'secondary' | 'default' | 'destructive'> = {
    draft: 'secondary',
    sent: 'outline',
    acknowledged: 'default',
    declined: 'destructive',
};

function formatTimestamp(value?: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const relative = formatDistanceToNow(date, { addSuffix: true });
    const absolute = format(date, 'PPpp');

    return `${relative} (${absolute})`;
}

function buildTooltipLines(
    status: AckState,
    latestDelivery?: PurchaseOrderDelivery | null,
    sentAt?: string | null,
    acknowledgedAt?: string | null,
    ackReason?: string | null,
): string[] {
    const lines: string[] = [];

    if (status === 'sent' && (sentAt || latestDelivery?.sentAt)) {
        lines.push(`Last sent ${formatTimestamp(latestDelivery?.sentAt ?? sentAt) ?? 'recently'}`);
    }

    if (status === 'acknowledged') {
        const formatted = formatTimestamp(acknowledgedAt);
        if (formatted) {
            lines.push(`Supplier acknowledged ${formatted}`);
        }
    }

    if (status === 'declined') {
        const formatted = formatTimestamp(acknowledgedAt ?? sentAt);
        if (formatted) {
            lines.push(`Supplier declined ${formatted}`);
        }
        if (ackReason) {
            lines.push(`Reason: ${ackReason}`);
        }
    }

    if (latestDelivery?.status === 'failed' && latestDelivery.errorReason) {
        lines.push(`Last delivery failed: ${latestDelivery.errorReason}`);
    }

    if (lines.length === 0 && status) {
        lines.push(`Status: ${STATUS_LABELS[status]}`);
    }

    return lines;
}

export interface AckStatusChipProps {
    status?: AckState;
    sentAt?: string | null;
    acknowledgedAt?: string | null;
    ackReason?: string | null;
    latestDelivery?: PurchaseOrderDelivery | null;
    className?: string;
}

export function AckStatusChip({
    status = 'draft',
    sentAt,
    acknowledgedAt,
    ackReason,
    latestDelivery,
    className,
}: AckStatusChipProps) {
    const tooltipLines = buildTooltipLines(status, latestDelivery, sentAt, acknowledgedAt, ackReason);
    const label = STATUS_LABELS[status] ?? 'Draft';
    const variant = STATUS_VARIANTS[status] ?? 'secondary';

    return (
        <TooltipProvider delayDuration={150}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge variant={variant} className={cn('uppercase tracking-wide', className)}>
                        {label}
                    </Badge>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs text-xs">
                    {tooltipLines.map((line, index) => (
                        <Fragment key={`${line}-${index}`}>
                            <span>{line}</span>
                            {index < tooltipLines.length - 1 ? <br /> : null}
                        </Fragment>
                    ))}
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}