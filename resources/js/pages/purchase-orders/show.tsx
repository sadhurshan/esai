import { EmptyState, StatusBadge } from '@/components/app';
import { errorToast, successToast } from '@/components/app/toasts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';

import { useApproveChangeOrder } from '@/hooks/api/useApproveChangeOrder';
import { usePoChangeOrders } from '@/hooks/api/usePoChangeOrders';
import { usePurchaseOrder } from '@/hooks/api/usePurchaseOrder';
import { useRejectChangeOrder } from '@/hooks/api/useRejectChangeOrder';
import { ApiError } from '@/lib/api';
import { formatCurrencyUSD, formatDate } from '@/lib/format';
import { home, purchaseOrders as purchaseOrderRoutes } from '@/routes';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { PurchaseOrderChangeOrder, PurchaseOrderLine } from '@/types/sourcing';

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

function resolveCompanyIdFromUser(user: unknown): number | null {
    if (!user || typeof user !== 'object') {
        return null;
    }

    const record = user as Record<string, unknown>;
    const candidate = record.company_id ?? record.companyId;

    if (typeof candidate === 'number') {
        return Number.isFinite(candidate) ? candidate : null;
    }

    if (typeof candidate === 'string') {
        const parsed = Number.parseInt(candidate, 10);
        return Number.isNaN(parsed) ? null : parsed;
    }

    return null;
}

function getErrorMessage(error: unknown): string {
    if (error instanceof ApiError) {
        return error.message;
    }

    if (error instanceof Error) {
        return error.message;
    }

    return 'Unexpected error occurred.';
}

export default function PurchaseOrdersShow() {
    const page = usePage<SharedData>();
    const pageProps = page.props as SharedData & Record<string, unknown>;
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
    const viewerCompanyId = resolveCompanyIdFromUser(page.props.auth?.user ?? null);
    const isBuyerView = Boolean(
        purchaseOrder && viewerCompanyId !== null && purchaseOrder.companyId === viewerCompanyId,
    );

    const changeOrderQueryId = isBuyerView ? purchaseOrderId : 0;
    const {
        data: changeOrderData,
        isLoading: isChangeOrdersLoading,
        isError: isChangeOrdersError,
        error: changeOrdersError,
        refetch: refetchChangeOrders,
    } = usePoChangeOrders(changeOrderQueryId);

    const approveMutation = useApproveChangeOrder();
    const rejectMutation = useRejectChangeOrder();
    const isProcessingChangeOrder = approveMutation.isPending || rejectMutation.isPending;
    const changeOrderItems = changeOrderData?.items ?? [];
    const changeOrders: PurchaseOrderChangeOrder[] = isBuyerView
        ? [...changeOrderItems].sort((a, b) => {
              const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0;
              const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0;
              return bTime - aTime;
          })
        : [];

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

    const handleApproveChangeOrder = async (changeOrderId: number) => {
        try {
            await approveMutation.mutateAsync({ changeOrderId, purchaseOrderId });
            successToast('Change order approved', 'Purchase order revision updated successfully.');
        } catch (err) {
            errorToast('Unable to approve change order', getErrorMessage(err));
        }
    };

    const handleRejectChangeOrder = async (changeOrderId: number) => {
        try {
            await rejectMutation.mutateAsync({ changeOrderId, purchaseOrderId });
            successToast('Change order rejected', 'The supplier will be notified.');
        } catch (err) {
            errorToast('Unable to reject change order', getErrorMessage(err));
        }
    };

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

                <Tabs defaultValue="line-items" className="flex flex-col gap-4">
                    <TabsList className="w-full justify-start overflow-x-auto">
                        <TabsTrigger value="line-items">Line Items</TabsTrigger>
                        {isBuyerView ? <TabsTrigger value="change-orders">Change Orders</TabsTrigger> : null}
                    </TabsList>

                    <TabsContent value="line-items">
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
                    </TabsContent>

                    {isBuyerView ? (
                        <TabsContent value="change-orders">
                            <Card className="border-muted/60">
                                <CardHeader>
                                    <CardTitle className="text-lg">Change Orders</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {isChangeOrdersLoading ? (
                                        <div className="space-y-3">
                                            {Array.from({ length: 3 }).map((_, index) => (
                                                <Skeleton key={`po-change-order-skeleton-${index}`} className="h-16 w-full" />
                                            ))}
                                        </div>
                                    ) : isChangeOrdersError ? (
                                        <EmptyState
                                            title="Unable to load change orders"
                                            description={changeOrdersError?.message ?? 'Please try again shortly.'}
                                            ctaLabel="Retry"
                                            ctaProps={{ onClick: () => refetchChangeOrders() }}
                                        />
                                    ) : changeOrders.length === 0 ? (
                                        <EmptyState
                                            title="No change orders"
                                            description="Supplier-requested revisions will appear here for review."
                                        />
                                    ) : (
                                        <div className="space-y-4">
                                            {changeOrders.map((changeOrder) => (
                                                <div
                                                    key={changeOrder.id}
                                                    className="rounded-lg border border-muted/60 p-4"
                                                >
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <div>
                                                            <p className="text-sm font-medium text-foreground">
                                                                Change Order #{changeOrder.id}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                Proposed {formatDate(changeOrder.createdAt)}{' '}
                                                                {changeOrder.proposedBy?.name
                                                                    ? `by ${changeOrder.proposedBy.name}`
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                        <StatusBadge status={changeOrder.status} />
                                                    </div>
                                                    <div className="mt-3 space-y-2 text-sm text-foreground">
                                                        <p className="font-medium">Reason</p>
                                                        <p className="text-muted-foreground">{changeOrder.reason}</p>
                                                    </div>
                                                    <div className="mt-3 space-y-2 text-sm text-foreground">
                                                        <p className="font-medium">Revision Number</p>
                                                        <p className="text-muted-foreground">
                                                            {typeof changeOrder.poRevisionNo === 'number'
                                                                ? `Revision ${changeOrder.poRevisionNo}`
                                                                : 'Pending approval'}
                                                        </p>
                                                    </div>
                                                    <div className="mt-3 space-y-2 text-sm text-foreground">
                                                        <p className="font-medium">Requested Changes</p>
                                                        <pre className="max-h-64 overflow-auto whitespace-pre-wrap break-words rounded-md bg-muted/40 p-3 text-xs text-muted-foreground">
                                                            {JSON.stringify(changeOrder.changes ?? {}, null, 2)}
                                                        </pre>
                                                    </div>
                                                    {changeOrder.status === 'proposed' ? (
                                                        <div className="mt-4 flex flex-wrap items-center justify-end gap-2">
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                disabled={isProcessingChangeOrder}
                                                                onClick={() => handleRejectChangeOrder(changeOrder.id)}
                                                            >
                                                                {rejectMutation.isPending ? 'Rejecting…' : 'Reject'}
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                disabled={isProcessingChangeOrder}
                                                                onClick={() => handleApproveChangeOrder(changeOrder.id)}
                                                            >
                                                                {approveMutation.isPending ? 'Approving…' : 'Approve'}
                                                            </Button>
                                                        </div>
                                                    ) : null}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </TabsContent>
                    ) : null}
                </Tabs>
            </div>
        </AppLayout>
    );
}
