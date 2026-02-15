import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface ItemStatusChipProps {
    status?: 'active' | 'inactive' | string;
    active?: boolean;
    className?: string;
}

export function ItemStatusChip({
    status,
    active,
    className,
}: ItemStatusChipProps) {
    const normalizedStatus = (
        status ?? (active ? 'active' : 'inactive')
    ).toLowerCase();
    const isActive = normalizedStatus === 'active';

    return (
        <Badge
            variant={isActive ? 'default' : 'outline'}
            className={cn(
                isActive
                    ? 'bg-emerald-100 text-emerald-900 hover:bg-emerald-100'
                    : 'text-muted-foreground',
                className,
            )}
        >
            {isActive ? 'Active' : 'Inactive'}
        </Badge>
    );
}
