import { Boxes, Filter, PackagePlus, RefreshCcw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ItemStatusChip } from '@/components/inventory/item-status-chip';
import { StockBadge } from '@/components/inventory/stock-badge';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAuth } from '@/contexts/auth-context';
import { useItems } from '@/hooks/api/inventory/use-items';
import { useUpdateItem } from '@/hooks/api/inventory/use-update-item';
import type { InventoryItemSummary } from '@/types/inventory';

const PER_PAGE = 25;

type CursorMeta = {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    [key: string]: unknown;
};

export function ItemListPage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');

    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState<
        'all' | 'active' | 'inactive'
    >('all');
    const [belowMinOnly, setBelowMinOnly] = useState(false);
    const [cursor, setCursor] = useState<string | null>(null);

    const itemsQuery = useItems({
        cursor,
        perPage: PER_PAGE,
        sku: search || undefined,
        name: search || undefined,
        category: categoryFilter || undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        belowMin: belowMinOnly || undefined,
    });

    const items = itemsQuery.data?.items ?? [];
    const cursorMeta = (itemsQuery.data?.meta ?? null) as CursorMeta | null;
    const nextCursor =
        typeof cursorMeta?.next_cursor === 'string'
            ? cursorMeta.next_cursor
            : null;
    const prevCursor =
        typeof cursorMeta?.prev_cursor === 'string'
            ? cursorMeta.prev_cursor
            : null;

    const updateItemMutation = useUpdateItem();

    const columns: DataTableColumn<InventoryItemSummary>[] = useMemo(
        () => [
            {
                key: 'sku',
                title: 'SKU',
                render: (item) => (
                    <Link
                        className="font-semibold text-primary"
                        to={`/app/inventory/items/${item.id}`}
                    >
                        {item.sku}
                    </Link>
                ),
            },
            {
                key: 'name',
                title: 'Name',
            },
            {
                key: 'category',
                title: 'Category',
                render: (item) => item.category ?? '—',
            },
            {
                key: 'defaultUom',
                title: 'Default UoM',
            },
            {
                key: 'onHand',
                title: 'On-hand',
                render: (item) => (
                    <StockBadge
                        onHand={item.onHand}
                        minStock={item.minStock ?? undefined}
                        uom={item.defaultUom}
                    />
                ),
            },
            {
                key: 'sitesCount',
                title: 'Sites',
                render: (item) => item.sitesCount ?? 0,
            },
            {
                key: 'status',
                title: 'Status',
                render: (item) => <ItemStatusChip status={item.status} />,
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (item) => (
                    <div className="flex items-center justify-end gap-2">
                        <Button asChild size="sm" variant="ghost">
                            <Link to={`/app/inventory/items/${item.id}`}>
                                Open
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={item.active ? 'outline' : 'default'}
                            disabled={updateItemMutation.isPending}
                            onClick={() =>
                                updateItemMutation.mutate({
                                    id: item.id,
                                    active: !item.active,
                                })
                            }
                        >
                            {item.active ? 'Deactivate' : 'Activate'}
                        </Button>
                    </div>
                ),
            },
        ],
        [updateItemMutation],
    );

    const handleResetFilters = () => {
        setSearch('');
        setCategoryFilter('');
        setStatusFilter('all');
        setBelowMinOnly(false);
        setCursor(null);
    };

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory · Items</title>
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
                <title>Inventory · Items</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Inventory
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Item master
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage SKUs, default UoMs, and reorder policies to keep
                        operations aligned.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    onClick={() => navigate('/app/inventory/items/new')}
                >
                    <PackagePlus className="mr-2 h-4 w-4" /> Create item
                </Button>
            </div>

            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-6 md:grid-cols-5">
                    <div className="space-y-2">
                        <Label>SKU or name</Label>
                        <Input
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setCursor(null);
                            }}
                            placeholder="Search catalog"
                        />
                        <p className="text-xs text-muted-foreground">
                            Partial matches allowed.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Category</Label>
                        <Input
                            value={categoryFilter}
                            onChange={(event) => {
                                setCategoryFilter(event.target.value);
                                setCursor(null);
                            }}
                            placeholder="Any category"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select
                            value={statusFilter}
                            onValueChange={(
                                value: 'all' | 'active' | 'inactive',
                            ) => {
                                setStatusFilter(value);
                                setCursor(null);
                            }}
                        >
                            <SelectTrigger className="h-9">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Inactive
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Below min only</Label>
                        <div className="flex items-center gap-3 rounded-md border border-border/60 px-3 py-2">
                            <Checkbox
                                id="below-min"
                                checked={belowMinOnly}
                                onCheckedChange={(checked) => {
                                    setBelowMinOnly(Boolean(checked));
                                    setCursor(null);
                                }}
                            />
                            <Label
                                htmlFor="below-min"
                                className="text-sm font-normal text-foreground"
                            >
                                Show items under threshold
                            </Label>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>&nbsp;</Label>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleResetFilters}
                            >
                                <RefreshCcw className="mr-2 h-4 w-4" /> Reset
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled
                            >
                                <Filter className="mr-2 h-4 w-4" /> Saved views
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <DataTable
                data={items}
                columns={columns}
                isLoading={itemsQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No items yet"
                        description="Create your first SKU to start tracking stock locations and reorder points."
                        icon={
                            <Boxes className="h-12 w-12 text-muted-foreground" />
                        }
                        ctaLabel="Create item"
                        ctaProps={{
                            onClick: () => navigate('/app/inventory/items/new'),
                        }}
                    />
                }
            />

            <div className="flex items-center justify-between rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-sm">
                <span className="text-muted-foreground">Cursor pagination</span>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setCursor(prevCursor ?? null)}
                        disabled={
                            itemsQuery.isLoading ||
                            (!prevCursor && cursor === null)
                        }
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => nextCursor && setCursor(nextCursor)}
                        disabled={itemsQuery.isLoading || !nextCursor}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
