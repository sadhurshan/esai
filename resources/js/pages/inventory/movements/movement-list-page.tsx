import {
    Boxes,
    FileSearch2,
    Filter,
    PlusCircle,
    RefreshCcw,
} from 'lucide-react';
import { useMemo, useState, type ChangeEvent } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useItems } from '@/hooks/api/inventory/use-items';
import { useLocations } from '@/hooks/api/inventory/use-locations';
import { useMovements } from '@/hooks/api/inventory/use-movements';
import type { MovementType } from '@/sdk';
import type { StockMovementSummary } from '@/types/inventory';

const MOVEMENTS_PER_PAGE = 25;

type CursorMeta = {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    [key: string]: unknown;
};

type MovementFilterState = {
    type: 'all' | MovementType;
    itemId: string;
    locationId: string;
    dateFrom: string;
    dateTo: string;
};

const defaultFilters: MovementFilterState = {
    type: 'all',
    itemId: '',
    locationId: '',
    dateFrom: '',
    dateTo: '',
};

const movementTypeOptions: Array<{
    label: string;
    value: MovementFilterState['type'];
}> = [
    { label: 'All types', value: 'all' },
    { label: 'Receipts', value: 'RECEIPT' },
    { label: 'Issues', value: 'ISSUE' },
    { label: 'Transfers', value: 'TRANSFER' },
    { label: 'Adjustments', value: 'ADJUST' },
];

const ANY_ITEM_VALUE = '__any-item__';
const ANY_LOCATION_VALUE = '__any-location__';

export function MovementListPage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const { formatDate, formatNumber } = useFormatting();
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');

    const [filters, setFilters] = useState<MovementFilterState>(defaultFilters);
    const [cursor, setCursor] = useState<string | null>(null);

    const movementQuery = useMovements({
        cursor,
        perPage: MOVEMENTS_PER_PAGE,
        type: filters.type === 'all' ? undefined : filters.type,
        itemId: filters.itemId || undefined,
        locationId: filters.locationId || undefined,
        dateFrom: filters.dateFrom || undefined,
        dateTo: filters.dateTo || undefined,
    });

    const data = movementQuery.data?.items ?? [];
    const cursorMeta = (movementQuery.data?.meta ?? null) as CursorMeta | null;
    const nextCursor =
        typeof cursorMeta?.next_cursor === 'string'
            ? cursorMeta.next_cursor
            : null;
    const prevCursor =
        typeof cursorMeta?.prev_cursor === 'string'
            ? cursorMeta.prev_cursor
            : null;

    const itemsQuery = useItems({ perPage: 100, status: 'active' });
    const locationsQuery = useLocations({ perPage: 100, type: 'bin' });
    const itemOptions = itemsQuery.data?.items ?? [];
    const locationOptions = locationsQuery.data?.items ?? [];

    const columns: DataTableColumn<StockMovementSummary>[] = useMemo(
        () => [
            {
                key: 'movementNumber',
                title: 'Movement #',
                render: (movement) => (
                    <Link
                        className="font-semibold text-primary"
                        to={`/app/inventory/movements/${movement.id}`}
                    >
                        {movement.movementNumber || '—'}
                    </Link>
                ),
            },
            {
                key: 'type',
                title: 'Type',
                render: (movement) => movement.type.toLowerCase(),
            },
            {
                key: 'lineCount',
                title: 'Lines',
                align: 'center',
                render: (movement) =>
                    formatNumber(movement.lineCount ?? 0, {
                        maximumFractionDigits: 0,
                    }),
            },
            {
                key: 'locations',
                title: 'From → To',
                render: (movement) => (
                    <span className="text-sm text-muted-foreground">
                        {movement.fromLocationName ?? '—'}
                        <span className="mx-2 text-muted-foreground/70">→</span>
                        {movement.toLocationName ?? '—'}
                    </span>
                ),
            },
            {
                key: 'reference',
                title: 'Reference',
                render: (movement) => movement.referenceLabel ?? '—',
            },
            {
                key: 'movedAt',
                title: 'Moved at',
                render: (movement) =>
                    formatDate(movement.movedAt, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    }),
            },
            {
                key: 'status',
                title: 'Status',
                render: (movement) => (
                    <Badge
                        variant={
                            movement.status === 'posted'
                                ? 'secondary'
                                : 'outline'
                        }
                        className="uppercase"
                    >
                        {movement.status}
                    </Badge>
                ),
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (movement) => (
                    <div className="flex items-center justify-end gap-2">
                        <Button asChild size="sm" variant="ghost">
                            <Link
                                to={`/app/inventory/movements/${movement.id}`}
                            >
                                Open
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ],
        [formatDate, formatNumber],
    );

    const handleFilterChange = <K extends keyof MovementFilterState>(
        key: K,
        value: MovementFilterState[K],
    ) => {
        setFilters((previous) => ({
            ...previous,
            [key]: value,
        }));
        setCursor(null);
    };

    const handleResetFilters = () => {
        setFilters(defaultFilters);
        setCursor(null);
    };

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory · Movements</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Inventory unavailable"
                    description="Upgrade your plan to review stock movements and history."
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
                <title>Inventory · Movements</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Inventory
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Stock movements
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Trace every receipt, issue, transfer, and adjustment.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    onClick={() => navigate('/app/inventory/movements/new')}
                >
                    <PlusCircle className="mr-2 h-4 w-4" /> Post movement
                </Button>
            </div>

            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-6 md:grid-cols-5">
                    <div className="space-y-2">
                        <Label>Movement type</Label>
                        <Select
                            value={filters.type}
                            onValueChange={(value) =>
                                handleFilterChange(
                                    'type',
                                    value as MovementFilterState['type'],
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Any type" />
                            </SelectTrigger>
                            <SelectContent>
                                {movementTypeOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Item</Label>
                        <Select
                            value={filters.itemId || ANY_ITEM_VALUE}
                            onValueChange={(value) =>
                                handleFilterChange(
                                    'itemId',
                                    value === ANY_ITEM_VALUE ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Any item" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY_ITEM_VALUE}>
                                    Any item
                                </SelectItem>
                                {itemsQuery.isLoading ? (
                                    <div className="px-3 py-2">
                                        <Skeleton className="h-4 w-32" />
                                    </div>
                                ) : (
                                    itemOptions.map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={item.id}
                                        >
                                            <div className="flex flex-col text-left">
                                                <span className="text-sm leading-tight font-medium">
                                                    {item.sku}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {item.name}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Location</Label>
                        <Select
                            value={filters.locationId || ANY_LOCATION_VALUE}
                            onValueChange={(value) =>
                                handleFilterChange(
                                    'locationId',
                                    value === ANY_LOCATION_VALUE ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Any location" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY_LOCATION_VALUE}>
                                    Any location
                                </SelectItem>
                                {locationsQuery.isLoading ? (
                                    <div className="px-3 py-2">
                                        <Skeleton className="h-4 w-28" />
                                    </div>
                                ) : (
                                    locationOptions.map((location) => (
                                        <SelectItem
                                            key={location.id}
                                            value={location.id}
                                        >
                                            <div className="flex flex-col text-left">
                                                <span className="text-sm leading-tight font-medium">
                                                    {location.name}
                                                </span>
                                                {location.siteName ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        {location.siteName}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </SelectItem>
                                    ))
                                )}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Pick a bin/site to scope history.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Date from</Label>
                        <Input
                            type="date"
                            value={filters.dateFrom}
                            onChange={(event: ChangeEvent<HTMLInputElement>) =>
                                handleFilterChange(
                                    'dateFrom',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Date to</Label>
                        <Input
                            type="date"
                            value={filters.dateTo}
                            onChange={(event: ChangeEvent<HTMLInputElement>) =>
                                handleFilterChange('dateTo', event.target.value)
                            }
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground md:col-span-5">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={handleResetFilters}
                        >
                            <RefreshCcw className="mr-2 h-4 w-4" /> Reset
                            filters
                        </Button>
                        <div className="inline-flex items-center gap-2 rounded-full bg-muted/60 px-3 py-1 text-[11px] tracking-wide uppercase">
                            <Filter className="h-3 w-3" /> Filters active
                        </div>
                    </div>
                </CardContent>
            </Card>

            <DataTable
                data={data}
                columns={columns}
                isLoading={movementQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No movements match"
                        description="Try adjusting your filters or posting a new movement."
                        icon={
                            <FileSearch2 className="h-10 w-10 text-muted-foreground" />
                        }
                        ctaLabel="Post movement"
                        ctaProps={{
                            onClick: () =>
                                navigate('/app/inventory/movements/new'),
                        }}
                    />
                }
            />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {movementQuery.isFetching
                        ? 'Loading results…'
                        : data.length === 0
                          ? 'No results'
                          : `${formatNumber(data.length, { maximumFractionDigits: 0 })} results`}
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!prevCursor}
                        onClick={() => setCursor(prevCursor)}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!nextCursor}
                        onClick={() => setCursor(nextCursor)}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
