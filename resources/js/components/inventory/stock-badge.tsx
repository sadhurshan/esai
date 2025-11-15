import { TrendingDown, TrendingUp } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface StockBadgeProps {
    onHand: number;
    minStock?: number | null;
    uom?: string;
    className?: string;
}

export function StockBadge({ onHand, minStock, uom = 'units', className }: StockBadgeProps) {
    const belowMin = typeof minStock === 'number' && Number.isFinite(minStock) && onHand < minStock;
    const icon = belowMin ? <TrendingDown className="h-3.5 w-3.5" /> : <TrendingUp className="h-3.5 w-3.5" />;

    return (
        <Badge
            variant={belowMin ? 'destructive' : 'secondary'}
            className={cn('flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium', className)}
        >
            {icon}
            <span>
                {formatQty(onHand)} {uom}
            </span>
            {typeof minStock === 'number' ? (
                <span className="text-[10px] text-muted-foreground">/ min {formatQty(minStock)}</span>
            ) : null}
        </Badge>
    );
}

function formatQty(value: number): string {
    if (!Number.isFinite(value)) {
        return '0';
    }
    if (Math.abs(value) >= 1) {
        return value.toLocaleString(undefined, { maximumFractionDigits: 1 });
    }
    return value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') || '0';
}
