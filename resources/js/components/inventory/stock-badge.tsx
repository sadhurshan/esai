import { TrendingDown, TrendingUp } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { useFormatting, type FormattingContextValue } from '@/contexts/formatting-context';

interface StockBadgeProps {
    onHand: number;
    minStock?: number | null;
    uom?: string;
    className?: string;
}

export function StockBadge({ onHand, minStock, uom = 'units', className }: StockBadgeProps) {
    const { formatNumber } = useFormatting();
    const belowMin = typeof minStock === 'number' && Number.isFinite(minStock) && onHand < minStock;
    const icon = belowMin ? <TrendingDown className="h-3.5 w-3.5" /> : <TrendingUp className="h-3.5 w-3.5" />;

    return (
        <Badge
            variant={belowMin ? 'destructive' : 'secondary'}
            className={cn('flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium', className)}
        >
            {icon}
            <span>
                {formatQty(onHand, formatNumber)} {uom}
            </span>
            {typeof minStock === 'number' ? (
                <span className="text-[10px] text-muted-foreground">/ min {formatQty(minStock, formatNumber)}</span>
            ) : null}
        </Badge>
    );
}

function formatQty(value: number, formatter: FormattingContextValue['formatNumber']): string {
    if (!Number.isFinite(value)) {
        return '0';
    }

    const precision = Math.abs(value) >= 1 ? 1 : 3;
    return formatter(value, { maximumFractionDigits: precision, fallback: '0' });
}
