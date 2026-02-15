import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    ClipboardList,
    Factory,
    PackagePlus,
    Repeat,
} from 'lucide-react';
import { useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { EmptyState } from '@/components/empty-state';
import { StockBadge } from '@/components/inventory/stock-badge';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useItems } from '@/hooks/api/inventory/use-items';
import { useLowStock } from '@/hooks/api/inventory/use-low-stock';
import { useMovements } from '@/hooks/api/inventory/use-movements';
import type { MovementType } from '@/sdk';

const MOVEMENT_TYPE_META: Record<
    MovementType,
    {
        label: string;
        variant: 'default' | 'secondary' | 'outline' | 'destructive';
    }
> = {
    RECEIPT: { label: 'Receipt', variant: 'default' },
    ISSUE: { label: 'Issue', variant: 'destructive' },
    TRANSFER: { label: 'Transfer', variant: 'secondary' },
    ADJUST: { label: 'Adjustment', variant: 'outline' },
};

const QUICK_ACTIONS: Array<{
    title: string;
    description: string;
    href: string;
    icon: LucideIcon;
}> = [
    {
        title: 'Item master',
        description: 'Catalog SKUs, categories, and reorder policies.',
        href: '/app/inventory/items',
        icon: ClipboardList,
    },
    {
        title: 'Stock movements',
        description: 'Post receipts, issues, and transfers between sites.',
        href: '/app/inventory/movements',
        icon: Repeat,
    },
    {
        title: 'Low stock alerts',
        description: 'See which SKUs are below minimum levels right now.',
        href: '/app/inventory/alerts/low-stock',
        icon: AlertTriangle,
    },
    {
        title: 'Receiving queue',
        description: 'Match inbound shipments to POs and update balances.',
        href: '/app/receiving',
        icon: Factory,
    },
];

export function InventoryPage() {
    const navigate = useNavigate();
    const { formatNumber, formatDate } = useFormatting();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');

    const itemsQuery = useItems({ perPage: 50, status: 'active' });
    const lowStockQuery = useLowStock({ perPage: 5 });
    const movementsQuery = useMovements({ perPage: 6 });

    const items = useMemo(
        () => itemsQuery.data?.items ?? [],
        [itemsQuery.data?.items],
    );
    const lowStockItems = useMemo(
        () => lowStockQuery.data?.items ?? [],
        [lowStockQuery.data?.items],
    );
    const recentMovements = useMemo(
        () => movementsQuery.data?.items ?? [],
        [movementsQuery.data?.items],
    );

    const movementsLast24h = useMemo(() => {
        // eslint-disable-next-line react-hooks/purity -- 24h snapshot must be relative to client clock per dashboard spec
        const now = Date.now();
        const dayAgo = now - 24 * 60 * 60 * 1000;
        return recentMovements.filter((movement) => {
            const movedAt = new Date(movement.movedAt).getTime();
            return Number.isFinite(movedAt) && movedAt >= dayAgo;
        }).length;
    }, [recentMovements]);

    const trackedSkuCount = useMemo(() => {
        return resolveMetaNumber(
            itemsQuery.data?.meta,
            [['totals', 'skus'], ['totals', 'items'], ['total'], ['count']],
            items.length,
        );
    }, [itemsQuery.data?.meta, items.length]);

    const lowStockCount = useMemo(() => {
        return resolveMetaNumber(
            lowStockQuery.data?.meta,
            [['totals', 'alerts'], ['total'], ['count']],
            lowStockItems.length,
        );
    }, [lowStockQuery.data?.meta, lowStockItems.length]);

    const stats = useMemo(() => {
        return [
            {
                label: 'Tracked SKUs',
                value: trackedSkuCount,
                description: 'Active catalog entries with balances.',
                icon: Boxes,
                loading: itemsQuery.isLoading,
            },
            {
                label: 'Below threshold',
                value: lowStockCount,
                description: 'Items currently under minimum stock.',
                icon: AlertTriangle,
                loading: lowStockQuery.isLoading,
            },
            {
                label: 'Movements (24h)',
                value: movementsLast24h,
                description: 'Receipts, issues, or transfers posted.',
                icon: Repeat,
                loading: movementsQuery.isLoading,
            },
        ];
    }, [
        trackedSkuCount,
        lowStockCount,
        movementsLast24h,
        itemsQuery.isLoading,
        lowStockQuery.isLoading,
        movementsQuery.isLoading,
    ]);

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Inventory unavailable"
                    description="Upgrade your plan to manage item masters, stock locations, and reorder policies."
                    icon={<Boxes className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{
                        onClick: () => navigate('/app/settings/billing'),
                    }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Inventory</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Supply operations
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Inventory control
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Track on-hand balances, stay ahead of low stock, and
                        keep every adjustment auditable.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => navigate('/app/inventory/movements/new')}
                    >
                        <Repeat className="mr-2 h-4 w-4" /> Log movement
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => navigate('/app/inventory/items/new')}
                    >
                        <PackagePlus className="mr-2 h-4 w-4" /> Create item
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                {stats.map((stat) => (
                    <Card key={stat.label} className="border-border/70">
                        <CardContent className="flex items-center gap-4 p-4">
                            <div className="rounded-full bg-muted p-2 text-muted-foreground">
                                <stat.icon className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    {stat.label}
                                </p>
                                {stat.loading ? (
                                    <Skeleton className="mt-2 h-7 w-16" />
                                ) : (
                                    <p className="mt-1 text-2xl font-semibold text-foreground">
                                        {formatNumber(stat.value)}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {stat.description}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Card className="border-border/70">
                <CardHeader className="gap-2 pb-2">
                    <CardTitle className="text-base font-semibold">
                        Workspace shortcuts
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Jump into the tools buyers and site managers use most
                        often.
                    </p>
                </CardHeader>
                <CardContent className="grid gap-3 md:grid-cols-2">
                    {QUICK_ACTIONS.map((action) => (
                        <Link
                            key={action.href}
                            to={action.href}
                            className="group rounded-lg border border-border/70 p-4 transition hover:border-primary"
                        >
                            <div className="flex items-start gap-3">
                                <div className="rounded-md bg-muted p-2 text-muted-foreground transition group-hover:text-primary">
                                    <action.icon className="h-5 w-5" />
                                </div>
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-semibold text-foreground">
                                            {action.title}
                                        </p>
                                        <ArrowRight className="h-4 w-4 text-muted-foreground transition group-hover:text-primary" />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {action.description}
                                    </p>
                                </div>
                            </div>
                        </Link>
                    ))}
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-[2fr,1fr]">
                <Card className="border-border/70">
                    <CardHeader className="flex flex-row items-start justify-between gap-4 pb-2">
                        <div>
                            <CardTitle className="text-base font-semibold">
                                Low stock alerts
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Prioritize SKUs that need replenishment to
                                protect uptime.
                            </p>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link to="/app/inventory/alerts/low-stock">
                                View all
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {lowStockQuery.isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 4 }).map((_, index) => (
                                    <Skeleton
                                        key={index}
                                        className="h-12 w-full"
                                    />
                                ))}
                            </div>
                        ) : lowStockItems.length === 0 ? (
                            <EmptyState
                                className="border-none bg-transparent"
                                title="No alerts"
                                description="All tracked items are above their reorder points."
                                icon={
                                    <Boxes className="h-10 w-10 text-muted-foreground" />
                                }
                            />
                        ) : (
                            <div className="divide-y divide-border/60">
                                {lowStockItems.map((alert) => (
                                    <div
                                        key={`${alert.itemId}-${alert.locationName ?? 'global'}`}
                                        className="flex flex-col gap-2 py-4 md:flex-row md:items-center md:justify-between"
                                    >
                                        <div>
                                            <Link
                                                to={`/app/inventory/items/${alert.itemId}`}
                                                className="font-semibold text-primary"
                                            >
                                                {alert.sku}
                                            </Link>
                                            <p className="text-sm text-muted-foreground">
                                                {alert.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {alert.locationName ??
                                                    'Multi-site'}{' '}
                                                ·{' '}
                                                {alert.category ??
                                                    'Uncategorized'}
                                            </p>
                                        </div>
                                        <div className="flex flex-col gap-1 text-sm text-muted-foreground">
                                            <StockBadge
                                                onHand={alert.onHand}
                                                minStock={alert.minStock}
                                                uom={alert.uom ?? undefined}
                                            />
                                            {alert.suggestedReorderDate && (
                                                <p className="text-xs">
                                                    Reorder by{' '}
                                                    {formatDate(
                                                        alert.suggestedReorderDate,
                                                        { dateStyle: 'medium' },
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader className="flex flex-row items-start justify-between gap-4 pb-2">
                        <div>
                            <CardTitle className="text-base font-semibold">
                                Recent movements
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Every transaction is audit-logged with source
                                and timestamp.
                            </p>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link to="/app/inventory/movements">View log</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {movementsQuery.isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 5 }).map((_, index) => (
                                    <Skeleton
                                        key={index}
                                        className="h-14 w-full"
                                    />
                                ))}
                            </div>
                        ) : recentMovements.length === 0 ? (
                            <EmptyState
                                className="border-none bg-transparent"
                                title="No movement history"
                                description="Log receipts, transfers, or adjustments to begin building traceability."
                                icon={
                                    <Repeat className="h-10 w-10 text-muted-foreground" />
                                }
                            />
                        ) : (
                            <div className="space-y-4">
                                {recentMovements.map((movement) => {
                                    const typeMeta = MOVEMENT_TYPE_META[
                                        movement.type
                                    ] ?? {
                                        label: movement.type,
                                        variant: 'outline' as const,
                                    };

                                    return (
                                        <div
                                            key={movement.id}
                                            className="rounded-lg border border-border/60 p-4"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <Link
                                                        to={`/app/inventory/movements/${movement.id}`}
                                                        className="font-semibold text-primary"
                                                    >
                                                        {
                                                            movement.movementNumber
                                                        }
                                                    </Link>
                                                    <p className="text-sm text-muted-foreground">
                                                        {movement.referenceLabel ??
                                                            'Manual entry'}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={typeMeta.variant}
                                                >
                                                    {typeMeta.label}
                                                </Badge>
                                            </div>
                                            <div className="mt-2 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                <span>
                                                    {formatDate(
                                                        movement.movedAt,
                                                        {
                                                            dateStyle: 'medium',
                                                            timeStyle: 'short',
                                                        },
                                                    )}
                                                </span>
                                                {movement.fromLocationName && (
                                                    <span>
                                                        From{' '}
                                                        {
                                                            movement.fromLocationName
                                                        }
                                                    </span>
                                                )}
                                                {movement.toLocationName && (
                                                    <span>
                                                        To{' '}
                                                        {
                                                            movement.toLocationName
                                                        }
                                                    </span>
                                                )}
                                                <span>
                                                    {movement.lineCount} line(s)
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function resolveMetaNumber(
    meta: Record<string, unknown> | null | undefined,
    paths: Array<string[]>,
    fallback: number,
): number {
    if (!meta || typeof meta !== 'object') {
        return fallback;
    }

    for (const path of paths) {
        let cursor: unknown = meta;
        let matched = true;

        for (const segment of path) {
            if (!cursor || typeof cursor !== 'object') {
                matched = false;
                break;
            }
            cursor = (cursor as Record<string, unknown>)[segment];
        }

        if (matched && typeof cursor === 'number') {
            return cursor;
        }
    }

    return fallback;
}
