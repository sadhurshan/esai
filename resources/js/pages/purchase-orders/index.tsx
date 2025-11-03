import { DataTable, EmptyState, Pagination, StatusBadge, FilterBar } from '@/components/app';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { usePurchaseOrders } from '@/hooks/api/usePurchaseOrders';
import { formatCurrencyUSD, formatDate } from '@/lib/format';
import { home, purchaseOrders as purchaseOrderRoutes } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { PurchaseOrderLine, PurchaseOrderSummary } from '@/types/sourcing';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Home', href: home().url },
    { title: 'Purchase Orders', href: purchaseOrderRoutes.index().url },
];

const STATUS_FILTER_OPTIONS = [
    { label: 'All statuses', value: '' },
    { label: 'Draft', value: 'draft' },
    { label: 'Sent', value: 'sent' },
    { label: 'Acknowledged', value: 'acknowledged' },
    { label: 'Confirmed', value: 'confirmed' },
    { label: 'Cancelled', value: 'cancelled' },
];

type PurchaseOrderRow = PurchaseOrderSummary & {
    totalValue: number;
    expectedDelivery?: string | null;
};

function calculateTotal(lines: PurchaseOrderLine[] = []): number {
    return lines.reduce((sum, line) => sum + line.quantity * line.unitPrice, 0);
}

function calculateExpectedDelivery(lines: PurchaseOrderLine[] = []): string | null {
    const dates = lines
        .map((line) => line.deliveryDate)
        .filter((value): value is string => Boolean(value));

    if (dates.length === 0) {
        return null;
    }

    const earliest = dates.sort((a, b) => new Date(a).getTime() - new Date(b).getTime())[0];
    return earliest ?? null;
}

export default function PurchaseOrdersIndex() {
    const [statusFilter, setStatusFilter] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError, error, refetch } = usePurchaseOrders({
        status: statusFilter || undefined,
        q: searchTerm || undefined,
        page,
        per_page: 10,
    });

    const tableRows: PurchaseOrderRow[] = useMemo(() => {
        const items = data?.items ?? [];
        return items.map((po) => ({
            ...po,
            totalValue: calculateTotal(po.lines ?? []),
            expectedDelivery: calculateExpectedDelivery(po.lines ?? []),
        }));
    }, [data]);

    const meta = data?.meta ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Purchase Orders" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">Purchase Orders</h1>
                    <p className="text-sm text-muted-foreground">
                        Monitor purchase order issuance, supplier acknowledgements, and downstream fulfillment milestones.
                    </p>
                </header>

                <FilterBar
                    searchPlaceholder="Search by PO number or supplier"
                    searchValue={searchTerm}
                    onSearchChange={(value) => {
                        setSearchTerm(value);
                        setPage(1);
                    }}
                    filters={[
                        {
                            id: 'status',
                            label: 'Status',
                            options: STATUS_FILTER_OPTIONS,
                            value: statusFilter,
                        },
                    ]}
                    onFilterChange={(id, value) => {
                        if (id === 'status') {
                            setStatusFilter(value);
                            setPage(1);
                        }
                    }}
                    onReset={() => {
                        setStatusFilter('');
                        setSearchTerm('');
                        setPage(1);
                    }}
                    isLoading={isLoading}
                />

                <DataTable<PurchaseOrderRow>
                    data={tableRows}
                    columns={[
                        {
                            key: 'poNumber',
                            title: 'PO Number',
                            render: (row) => (
                                <Button variant="link" size="sm" asChild>
                                    <Link href={purchaseOrderRoutes.show({ id: row.id }).url}>
                                        {row.poNumber}
                                    </Link>
                                </Button>
                            ),
                        },
                        {
                            key: 'supplierName',
                            title: 'Supplier',
                            render: (row) => row.supplierName ?? '—',
                        },
                        {
                            key: 'totalValue',
                            title: 'Total',
                            align: 'right',
                            render: (row) => formatCurrencyUSD(row.totalValue),
                        },
                        {
                            key: 'expectedDelivery',
                            title: 'Expected Delivery',
                            render: (row) => formatDate(row.expectedDelivery),
                        },
                        {
                            key: 'status',
                            title: 'Status',
                            render: (row) => <StatusBadge status={row.status} />,
                        },
                    ]}
                    isLoading={isLoading}
                    emptyState={
                        isError ? (
                            <EmptyState
                                title="Unable to load purchase orders"
                                description={error?.message ?? 'Please try again.'}
                                ctaLabel="Retry"
                                ctaProps={{ onClick: () => refetch() }}
                            />
                        ) : (
                            <EmptyState
                                title="No purchase orders yet"
                                description="Award supplier quotes to generate purchase orders that will display here."
                            />
                        )
                    }
                />

                <Pagination meta={meta} onPageChange={setPage} isLoading={isLoading} />
            </div>
        </AppLayout>
    );
}
