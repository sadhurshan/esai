import { lazy, Suspense, type ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';

import type { SupplierRiskBadgeProps } from './SupplierRiskBadge';

const SupplierRiskBadgeLazy = lazy(async () => ({
    default: (await import('./SupplierRiskBadge')).SupplierRiskBadge,
}));

interface LazySupplierRiskBadgeProps extends SupplierRiskBadgeProps {
    fallback?: ReactNode;
}

export function LazySupplierRiskBadge({ fallback, ...props }: LazySupplierRiskBadgeProps) {
    return (
        <Suspense
            fallback={
                fallback ?? (
                    <Badge variant="outline" className="text-[11px] text-muted-foreground">
                        AI risk
                    </Badge>
                )
            }
        >
            <SupplierRiskBadgeLazy {...props} />
        </Suspense>
    );
}
