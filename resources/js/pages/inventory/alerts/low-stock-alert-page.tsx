import { AlertTriangle, Boxes, ClipboardPlus, RefreshCcw } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useLocations } from '@/hooks/api/inventory/use-locations';
import { useLowStock } from '@/hooks/api/inventory/use-low-stock';
import {
    createPrefillFromAlerts,
    saveLowStockRfqPrefill,
} from '@/lib/low-stock-rfq-prefill';
import type { LowStockAlertRow } from '@/types/inventory';

const ALERTS_PER_PAGE = 25;

type CursorMeta = {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    [key: string]: unknown;
};

type FilterState = {
    siteId: string;
    locationId: string;
    category: string;
};

const defaultFilters: FilterState = {
    siteId: '',
    locationId: '',
    category: '',
};

const ANY_OPTION_VALUE = '__any__';

export function LowStockAlertPage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const inventoryEnabled = hasFeature('inventory_enabled');

    const [filters, setFilters] = useState<FilterState>(defaultFilters);
    const [cursor, setCursor] = useState<string | null>(null);
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

    const lowStockQuery = useLowStock({
        cursor,
        perPage: ALERTS_PER_PAGE,
        siteId: filters.siteId || undefined,
        locationId: filters.locationId || undefined,
        category: filters.category || undefined,
    });

    const siteOptionsQuery = useLocations({ perPage: 100, type: 'site' });
    const locationOptionsQuery = useLocations({ perPage: 100, type: 'bin' });

    const siteOptions = siteOptionsQuery.data?.items ?? [];
    const locationOptions = locationOptionsQuery.data?.items ?? [];

    const rows = useMemo(
        () => lowStockQuery.data?.items ?? [],
        [lowStockQuery.data?.items],
    );
    const cursorMeta = (lowStockQuery.data?.meta ?? null) as CursorMeta | null;
    const nextCursor =
        typeof cursorMeta?.next_cursor === 'string'
            ? cursorMeta.next_cursor
            : null;
    const prevCursor =
        typeof cursorMeta?.prev_cursor === 'string'
            ? cursorMeta.prev_cursor
            : null;

    const selectedAlerts = useMemo(
        () =>
            rows.filter(
                (row) =>
                    typeof row.itemId === 'string' &&
                    selectedIds.has(row.itemId),
            ),
        [rows, selectedIds],
    );

    const toggleRowSelection = useCallback(
        (itemId?: string | null, checked?: boolean) => {
            if (!itemId) {
                return;
            }
            setSelectedIds((previous) => {
                const next = new Set(previous);
                if (checked) {
                    next.add(itemId);
                } else {
                    next.delete(itemId);
                }
                return next;
            });
        },
        [],
    );

    const clearSelection = useCallback(() => {
        setSelectedIds(new Set());
    }, []);

    const prefillAndNavigate = useCallback(
        (alerts: LowStockAlertRow[]) => {
            if (alerts.length === 0) {
                publishToast({
                    variant: 'destructive',
                    title: 'Select at least one item',
                    description:
                        'Choose one or more alerts to prefill the RFQ wizard.',
                });
                return;
            }

            const payload = createPrefillFromAlerts(alerts);

            if (payload.length === 0) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to prefill RFQ',
                    description:
                        'No valid quantities were detected for the selected alerts.',
                });
                return;
            }

            saveLowStockRfqPrefill(payload);
            clearSelection();
            navigate('/app/rfqs/new');
        },
        [clearSelection, navigate],
    );

    const columns: DataTableColumn<LowStockAlertRow>[] = useMemo(
        () => [
            {
                key: 'select',
                title: 'Select',
                width: '64px',
                render: (row) => (
                    <Checkbox
                        aria-label={`Select ${row.sku}`}
                        checked={
                            row.itemId ? selectedIds.has(row.itemId) : false
                        }
                        onCheckedChange={(checked) =>
                            toggleRowSelection(row.itemId, checked === true)
                        }
                    />
                ),
            },
            {
                key: 'sku',
                title: 'SKU',
                render: (row) => (
                    <Link
                        className="font-semibold text-primary"
                        to={`/app/inventory/items/${row.itemId}`}
                    >
                        {row.sku}
                    </Link>
                ),
            },
            {
                key: 'name',
                title: 'Item name',
            },
            {
                key: 'locationName',
                title: 'Location',
                render: (row) => row.locationName ?? row.siteName ?? '—',
            },
            {
                key: 'onHand',
                title: 'On-hand',
                align: 'center',
            },
            {
                key: 'minStock',
                title: 'Min',
                align: 'center',
            },
            {
                key: 'reorderQty',
                title: 'Reorder qty',
                align: 'center',
                render: (row) => row.reorderQty ?? '—',
            },
            {
                key: 'leadTimeDays',
                title: 'Lead time (days)',
                align: 'center',
                render: (row) => row.leadTimeDays ?? '—',
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (row) => (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => prefillAndNavigate([row])}
                    >
                        Prefill RFQ
                    </Button>
                ),
            },
        ],
        [prefillAndNavigate, selectedIds, toggleRowSelection],
    );

    const handleFilterChange = <K extends keyof FilterState>(
        key: K,
        value: FilterState[K],
    ) => {
        setFilters((previous) => ({ ...previous, [key]: value }));
        setCursor(null);
        clearSelection();
    };

    const handleReset = () => {
        setFilters(defaultFilters);
        setCursor(null);
        clearSelection();
    };

    const handlePageChange = (nextCursor: string | null) => {
        setCursor(nextCursor);
        clearSelection();
    };

    if (featureFlagsLoaded && !inventoryEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Inventory · Low stock</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Inventory unavailable"
                    description="Upgrade your plan to monitor low-stock alerts."
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
                <title>Inventory · Low stock alerts</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="space-y-1">
                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                    Inventory
                </p>
                <h1 className="text-2xl font-semibold text-foreground">
                    Low-stock alerts
                </h1>
                <p className="text-sm text-muted-foreground">
                    Review SKUs that have fallen below their safety stock
                    thresholds.
                </p>
            </div>

            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-6 md:grid-cols-4">
                    <div className="space-y-2">
                        <Label>Site</Label>
                        <Select
                            value={filters.siteId || ANY_OPTION_VALUE}
                            onValueChange={(value) =>
                                handleFilterChange(
                                    'siteId',
                                    value === ANY_OPTION_VALUE ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Any site" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY_OPTION_VALUE}>
                                    Any site
                                </SelectItem>
                                {siteOptions.map((site) => (
                                    <SelectItem key={site.id} value={site.id}>
                                        {site.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Location</Label>
                        <Select
                            value={filters.locationId || ANY_OPTION_VALUE}
                            onValueChange={(value) =>
                                handleFilterChange(
                                    'locationId',
                                    value === ANY_OPTION_VALUE ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Any location" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY_OPTION_VALUE}>
                                    Any location
                                </SelectItem>
                                {locationOptions.map((location) => (
                                    <SelectItem
                                        key={location.id}
                                        value={location.id}
                                    >
                                        {location.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Category</Label>
                        <Input
                            value={filters.category}
                            onChange={(event) =>
                                handleFilterChange(
                                    'category',
                                    event.target.value,
                                )
                            }
                            placeholder="e.g. Fasteners"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label className="text-transparent">Reset</Label>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleReset}
                        >
                            <RefreshCcw className="mr-2 h-4 w-4" /> Reset
                            filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card className="border-border/70">
                <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            At risk
                        </p>
                        <h2 className="text-lg font-semibold text-foreground">
                            {rows.length} items below threshold
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {selectedAlerts.length > 0
                                ? `${selectedAlerts.length} selected for RFQ prefill.`
                                : 'Select alerts to prefill the RFQ wizard.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            onClick={() => prefillAndNavigate(selectedAlerts)}
                            disabled={selectedAlerts.length === 0}
                        >
                            <ClipboardPlus className="mr-2 h-4 w-4" /> Prefill
                            RFQ
                        </Button>
                        <Button type="button" variant="secondary" asChild>
                            <Link to="/app/rfqs/new">Launch RFQ</Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <DataTable
                        data={rows}
                        columns={columns}
                        isLoading={lowStockQuery.isLoading}
                        emptyState={
                            <div className="flex flex-col items-center gap-3 text-center">
                                <AlertTriangle className="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p className="font-semibold text-foreground">
                                        No alerts
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        All tracked SKUs are above minimum
                                        stock.
                                    </p>
                                </div>
                            </div>
                        }
                    />
                </CardContent>
            </Card>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {rows.length} results
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!prevCursor}
                        onClick={() => handlePageChange(prevCursor)}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!nextCursor}
                        onClick={() => handlePageChange(nextCursor)}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
