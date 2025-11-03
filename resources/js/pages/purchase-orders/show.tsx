import { EmptyState, StatusBadge } from '@/components/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import { usePurchaseOrder } from '@/hooks/api/usePurchaseOrder';
import { formatCurrencyUSD, formatDate } from '@/lib/format';
import { home, purchaseOrders as purchaseOrderRoutes } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { PurchaseOrderLine } from '@/types/sourcing';

function parsePurchaseOrderId(url: string): number | null {
    const [path] = url.split('?');
    const segments = path.split('/').filter(Boolean);
    const purchaseOrdersIndex = segments.indexOf('purchase-orders');

    if (purchaseOrdersIndex === -1) {
        return null;
    }

    const potentialId = segments[purchaseOrdersIndex + 1];
    if (!potentialId) {
        return null;
    }

    const parsed = Number.parseInt(potentialId, 10);
    return Number.isNaN(parsed) ? null : parsed;
}

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

export default function PurchaseOrdersShow() {
    const page = usePage();
    const pageProps = page.props as Record<string, unknown>;
    const derivedId = useMemo(() => {
        const pageId = pageProps?.id;

        if (typeof pageId === 'number') {
            return pageId;
        }

        return parsePurchaseOrderId(page.url);
    }, [pageProps, page.url]);

    const purchaseOrderId = derivedId ?? 0;
    const { data, isLoading, isError, error, refetch } = usePurchaseOrder(purchaseOrderId);

    const purchaseOrder = data;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Home', href: home().url },
        { title: 'Purchase Orders', href: purchaseOrderRoutes.index().url },
        {
            title: purchaseOrder?.poNumber ?? `PO ${purchaseOrderId}`,
            href: purchaseOrderRoutes.show({ id: purchaseOrderId || 0 }).url,
        },
    ];

    if (!purchaseOrderId) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Purchase Order" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Purchase order not found"
                        description="A valid purchase order identifier is required to load this page."
                    />
                </div>
            </AppLayout>
        );
    }

    if (isError) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Purchase Order" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Unable to load purchase order"
                        description={error?.message ?? 'Please try again.'}
                        ctaLabel="Retry"
                        ctaProps={{ onClick: () => refetch() }}
                    />
                </div>
            </AppLayout>
        );
    }

    if (!isLoading && !purchaseOrder) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Purchase Order" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Purchase order unavailable"
                        description="This purchase order could not be located or you do not have access."
                    />
                </div>
            </AppLayout>
        );
    }

    const lines = purchaseOrder?.lines ?? [];
    const totalValue = purchaseOrder ? calculateTotal(lines) : 0;
    const expectedDeliveryDate = purchaseOrder ? calculateExpectedDelivery(lines) : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={purchaseOrder ? `PO ${purchaseOrder.poNumber}` : 'Purchase Order'} />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        {isLoading ? (
                            <>
                                <Skeleton className="h-8 w-56" />
                                <Skeleton className="h-5 w-64" />
                            </>
                        ) : (
                            <>
                                <h1 className="text-2xl font-semibold text-foreground">
                                    Purchase Order {purchaseOrder?.poNumber}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {purchaseOrder?.supplierName ?? 'Supplier pending'}
                                </p>
                            </>
                        )}
                    </div>
                    {isLoading ? (
                        <Skeleton className="h-6 w-24" />
                    ) : purchaseOrder ? (
                        <StatusBadge status={purchaseOrder.status} />
                    ) : null}
                </div>

                <section className="grid gap-4 md:grid-cols-[3fr_1fr]">
                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">Purchase Order Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {isLoading || !purchaseOrder ? (
                                Array.from({ length: 6 }).map((_, index) => (
                                    <div key={`po-summary-skeleton-${index}`} className="space-y-2">
                                        <Skeleton className="h-3 w-24" />
                                        <Skeleton className="h-4 w-36" />
                                    </div>
                                ))
                            ) : (
                                <>
                                    <div>
                                        <p className="text-xs uppercase text-muted-foreground">PO Number</p>
                                        <p className="text-sm text-foreground">{purchaseOrder.poNumber}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs uppercase text-muted-foreground">Supplier</p>
                                        <p className="text-sm text-foreground">
                                            {purchaseOrder.supplierName ?? 'Not assigned'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs uppercase text-muted-foreground">Expected Delivery</p>
                                        <p className="text-sm text-foreground">{formatDate(expectedDeliveryDate)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs uppercase text-muted-foreground">Total Value</p>
                                        <p className="text-sm text-foreground">{formatCurrencyUSD(totalValue)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs uppercase text-muted-foreground">Incoterm</p>
                                        <p className="text-sm text-foreground">{purchaseOrder.incoterm ?? 'Not specified'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs uppercase text-muted-foreground">Revision</p>
                                        <p className="text-sm text-foreground">Rev {purchaseOrder.revisionNo}</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-muted/60">
                        <CardHeader>
                            <CardTitle className="text-lg">Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Send the purchase order to the supplier when commercial terms are ready to confirm.
                            </p>
                            <Button disabled size="sm" className="w-full">
                                Send PO
                            </Button>
                            <Button variant="outline" size="sm" asChild className="w-full">
                                <Link href={purchaseOrderRoutes.index().url}>Back to list</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </section>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Line Items</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {isLoading ? (
                            <div className="space-y-3">
                                {Array.from({ length: 4 }).map((_, index) => (
                                    <Skeleton key={`po-line-skeleton-${index}`} className="h-10 w-full" />
                                ))}
                            </div>
                        ) : lines.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-muted/60 text-sm">
                                    <thead className="bg-muted/40">
                                        <tr className="text-left text-xs font-semibold uppercase text-muted-foreground">
                                            <th className="px-4 py-3">Line</th>
                                            <th className="px-4 py-3">Description</th>
                                            <th className="px-4 py-3">Quantity</th>
                                            <th className="px-4 py-3">Unit Price</th>
                                            <th className="px-4 py-3">Line Total</th>
                                            <th className="px-4 py-3">Delivery Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-muted/40">
                                        {lines.map((line) => (
                                            <tr key={line.id}>
                                                <td className="px-4 py-3 font-medium text-foreground">{line.lineNo}</td>
                                                <td className="px-4 py-3 text-foreground">{line.description}</td>
                                                <td className="px-4 py-3 text-foreground">
                                                    {line.quantity.toLocaleString()} {line.uom}
                                                </td>
                                                <td className="px-4 py-3 text-foreground">
                                                    {formatCurrencyUSD(line.unitPrice)}
                                                </td>
                                                <td className="px-4 py-3 text-foreground">
                                                    {formatCurrencyUSD(line.quantity * line.unitPrice)}
                                                </td>
                                                <td className="px-4 py-3 text-foreground">{formatDate(line.deliveryDate)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <EmptyState
                                title="No line items"
                                description="Line items will display once the purchase order has been generated from an awarded quote."
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
