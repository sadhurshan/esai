import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'secondary',
    inspecting: 'outline',
    accepted: 'default',
    posted: 'default',
    rejected: 'destructive',
    variance: 'destructive',
};

interface GrnStatusBadgeProps {
    status: string;
    className?: string;
}

export function GrnStatusBadge({ status, className }: GrnStatusBadgeProps) {
    const normalized = status?.toLowerCase?.() ?? 'draft';
    const variant = STATUS_VARIANTS[normalized] ?? 'outline';

    return (
        <Badge variant={variant} className={cn('uppercase tracking-wide', className)}>
            {status}
        </Badge>
    );
}
