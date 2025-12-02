import { useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, ExternalLink, Package, Truck } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import { OrderLinesTable } from '@/components/orders/order-lines-table';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
import { ShipmentStatusChip } from '@/components/orders/shipment-status-chip';
import { OrderTimeline } from '@/components/orders/order-timeline';
import { useFormatting } from '@/contexts/formatting-context';
import { useBuyerOrder } from '@/hooks/api/orders/use-buyer-order';
import type { SalesOrderShipment } from '@/types/orders';

function buildTrackingUrl(carrier?: string | null, trackingNumber?: string | null): string | null {
    if (!trackingNumber) {
        return null;
    }
    const query = [carrier, trackingNumber].filter(Boolean).join(' ');
    return `https://www.google.com/search?q=${encodeURIComponent(query || trackingNumber)}`;
}

export function BuyerOrderDetailPage() {
    const params = useParams<{ soId?: string }>();
    const navigate = useNavigate();
    const { formatDate, formatMoney, formatNumber } = useFormatting();
    const orderId = params.soId;

    const buyerOrderQuery = useBuyerOrder(orderId ?? null, { enabled: Boolean(orderId) });
    const order = buyerOrderQuery.data;
    const acknowledgement = useMemo(() => {
        if (!order?.acknowledgements?.length) {
            return undefined;
        }
        return order.acknowledgements[order.acknowledgements.length - 1];
    }, [order?.acknowledgements]);

    if (!orderId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Order tracking</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Order reference missing"
                    description="Select an order from the list to view shipment details."
                    icon={<AlertTriangle className="h-12 w-12 text-destructive" />}
                    ctaLabel="Back to orders"
                    ctaProps={{ onClick: () => navigate('/app/orders') }}
                />
            </div>
        );
    }

    if (buyerOrderQuery.isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Order tracking</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <Skeleton className="h-48 w-full rounded-xl" />
                <Skeleton className="h-96 w-full rounded-xl" />
            </div>
        );
    }

    if (buyerOrderQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Order tracking</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load order"
                    description="We could not retrieve shipment details. Try again or contact the supplier."
                    icon={<AlertTriangle className="h-12 w-12 text-destructive" />}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => buyerOrderQuery.refetch() }}
                />
            </div>
        );
    }

    if (!order) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Order tracking</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Order not found"
                    description="This sales order mirror no longer exists or access was revoked."
                    icon={<AlertTriangle className="h-12 w-12 text-destructive" />}
                    ctaLabel="Back to orders"
                    ctaProps={{ onClick: () => navigate('/app/orders') }}
                />
            </div>
        );
    }

    const fulfillmentOrdered =
        order.fulfillment?.orderedQty ?? order.lines?.reduce((acc, line) => acc + (line.qtyOrdered ?? 0), 0) ?? 0;
    const fulfillmentShipped = order.fulfillment?.shippedQty ?? order.lines?.reduce((acc, line) => acc + (line.qtyShipped ?? 0), 0) ?? 0;
    const fulfillmentPercent =
        fulfillmentOrdered > 0 ? Math.min(100, Math.round((fulfillmentShipped / fulfillmentOrdered) * 100)) : 0;
    const shipments = order.shipments ?? [];

    const renderShipment = (shipment: SalesOrderShipment) => (
        <div key={shipment.id} className="rounded-lg border border-border/60 p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold text-foreground">Shipment {shipment.shipmentNo}</p>
                    <p className="text-sm text-muted-foreground">{shipment.carrier ?? 'Carrier pending'}</p>
                    {shipment.trackingNumber ? (
                        <a
                            className="inline-flex items-center gap-1 text-sm text-primary"
                            href={buildTrackingUrl(shipment.carrier, shipment.trackingNumber) ?? undefined}
                            target="_blank"
                            rel="noreferrer"
                        >
                            {shipment.trackingNumber}
                            <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                    ) : (
                        <span className="text-sm text-muted-foreground">Tracking number pending</span>
                    )}
                </div>
                <div className="text-right">
                    <ShipmentStatusChip status={shipment.status} className="justify-end" />
                    <p className="text-xs text-muted-foreground">Shipped {formatDate(shipment.shippedAt)}</p>
                    {shipment.deliveredAt ? (
                        <p className="text-xs text-muted-foreground">Delivered {formatDate(shipment.deliveredAt)}</p>
                    ) : null}
                </div>
            </div>
            <Separator className="my-3" />
            <div className="grid gap-3 text-sm md:grid-cols-2">
                {shipment.lines.map((line) => (
                    <div key={line.soLineId} className="flex items-center justify-between">
                        <span className="text-muted-foreground">Line #{line.soLineId}</span>
                        <span className="font-medium text-foreground">
                            {formatNumber(line.qtyShipped, { maximumFractionDigits: 3 })}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Order {order.soNumber}</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <Card className="border-border/70">
                <CardHeader className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="space-y-1">
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Buyer workspace</p>
                        <CardTitle className="text-3xl font-semibold text-foreground">Sales order {order.soNumber}</CardTitle>
                        <CardDescription>
                            Mirror of PO #{order.poId}. Monitor acknowledgements and shipment events from the supplier portal.
                        </CardDescription>
                        <div className="flex flex-wrap gap-2 pt-2 text-sm text-muted-foreground">
                            <Badge variant="outline" className="border-primary/50 text-primary">
                                Supplier: {order.supplierCompanyName ?? `#${order.supplierCompanyId}`}
                            </Badge>
                            <Badge variant="outline" className="gap-1">
                                <Package className="h-3.5 w-3.5" /> PO #{order.poId}
                            </Badge>
                        </div>
                    </div>
                    <OrderStatusBadge status={order.status} />
                </CardHeader>
                <CardContent className="space-y-6">
                    <dl className="grid gap-4 text-sm md:grid-cols-3">
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Issue date</dt>
                            <dd className="font-medium text-foreground">{formatDate(order.issueDate)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Due date</dt>
                            <dd className="font-medium text-foreground">{formatDate(order.dueDate)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Order total</dt>
                            <dd className="font-medium text-foreground">
                                {formatMoney(
                                    order.totals?.totalMinor !== undefined && order.totals?.totalMinor !== null
                                        ? order.totals.totalMinor / 100
                                        : null,
                                    { currency: order.currency },
                                )}
                            </dd>
                        </div>
                    </dl>

                    <div className="rounded-lg border border-border/60 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Fulfilment progress</p>
                                <p className="text-sm text-muted-foreground">
                                    {formatNumber(fulfillmentShipped, { maximumFractionDigits: 2 })} of{' '}
                                    {formatNumber(fulfillmentOrdered, { maximumFractionDigits: 2 })} units shipped
                                </p>
                            </div>
                            <span className="text-lg font-semibold text-foreground">{fulfillmentPercent}%</span>
                        </div>
                        <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${fulfillmentPercent}%` }} />
                        </div>
                    </div>

                    {acknowledgement ? (
                        <Card className="border-dashed border-muted-foreground/50">
                            <CardContent className="space-y-2 py-4">
                                <p className="text-sm font-semibold text-foreground">
                                    Supplier {acknowledgement.decision === 'accept' ? 'accepted' : 'declined'} on{' '}
                                    {formatDate(acknowledgement.acknowledgedAt)}
                                </p>
                                {acknowledgement.reason ? (
                                    <p className="text-sm text-muted-foreground">“{acknowledgement.reason}”</p>
                                ) : null}
                            </CardContent>
                        </Card>
                    ) : null}
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle className="text-lg font-semibold">Shipping profile</CardTitle>
                        <CardDescription>Reference addresses and instructions shared with the supplier.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm">
                            <div>
                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">Ship to</dt>
                                <dd className="font-medium text-foreground">{order.shipping?.shipToName ?? '—'}</dd>
                                {order.shipping?.shipToAddress ? (
                                    <p className="text-xs text-muted-foreground">{order.shipping.shipToAddress}</p>
                                ) : null}
                            </div>
                            <div>
                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">Incoterm</dt>
                                <dd className="font-medium text-foreground">{order.shipping?.incoterm ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">Carrier preference</dt>
                                <dd className="font-medium text-foreground">{order.shipping?.carrierPreference ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs uppercase tracking-wide text-muted-foreground">Instructions</dt>
                                <dd className="text-sm text-muted-foreground">{order.shipping?.instructions ?? '—'}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle className="text-lg font-semibold">Linked records</CardTitle>
                        <CardDescription>Jump to the original purchase order for change orders or receiving.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3 text-sm">
                        <Button asChild variant="outline" size="sm">
                            <Link to={`/app/purchase-orders/${order.poId}`} className="gap-2">
                                <Package className="h-4 w-4" /> Open purchase order
                            </Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link to={`/app/receiving?poId=${order.poId}`} className="gap-2">
                                <ExternalLink className="h-4 w-4" /> Receiving workspace
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <Tabs defaultValue="lines" className="space-y-4">
                <TabsList className="w-full justify-start gap-2 overflow-x-auto">
                    <TabsTrigger value="lines">Lines</TabsTrigger>
                    <TabsTrigger value="shipments">Shipments</TabsTrigger>
                    <TabsTrigger value="timeline">Timeline</TabsTrigger>
                </TabsList>

                <TabsContent value="lines">
                    <OrderLinesTable lines={order.lines ?? []} totals={order.totals} />
                </TabsContent>

                <TabsContent value="shipments">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-lg font-semibold">Shipments</CardTitle>
                            <CardDescription>Tracking numbers, carriers, and delivery confirmations from the supplier.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {shipments.length === 0 ? (
                                <EmptyState
                                    title="No shipments posted"
                                    description="Once the supplier dispatches goods and logs shipments they will appear here."
                                    icon={<Truck className="h-10 w-10 text-muted-foreground" />}
                                />
                            ) : (
                                shipments.map((shipment) => renderShipment(shipment))
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="timeline">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-lg font-semibold">Timeline</CardTitle>
                            <CardDescription>Combined acknowledgement, shipment, and status activity.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <OrderTimeline
                                entries={order.timeline}
                                isLoading={buyerOrderQuery.isFetching}
                                onRetry={() => buyerOrderQuery.refetch()}
                                emptyLabel="No events recorded yet."
                            />
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    );
}
