import {
    AlertTriangle,
    CheckCircle2,
    ExternalLink,
    Package,
    PackagePlus,
    Repeat2,
    Truck,
    Undo2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { EmptyState } from '@/components/empty-state';
import { OrderLinesTable } from '@/components/orders/order-lines-table';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
import { OrderTimeline } from '@/components/orders/order-timeline';
import { ShipmentCreateDialog } from '@/components/orders/shipment-create-dialog';
import { ShipmentStatusChip } from '@/components/orders/shipment-status-chip';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useFormatting } from '@/contexts/formatting-context';
import { useAckOrder } from '@/hooks/api/orders/use-ack-order';
import { useCreateShipment } from '@/hooks/api/orders/use-create-shipment';
import { useSupplierOrder } from '@/hooks/api/orders/use-supplier-order';
import { useUpdateShipmentStatus } from '@/hooks/api/orders/use-update-shipment-status';
import type {
    CreateShipmentPayload,
    SalesOrderDetail,
    SalesOrderShipment,
} from '@/types/orders';

function buildTrackingUrl(
    carrier?: string | null,
    trackingNumber?: string | null,
): string | null {
    if (!trackingNumber) {
        return null;
    }
    const query = [carrier, trackingNumber].filter(Boolean).join(' ');
    return `https://www.google.com/search?q=${encodeURIComponent(query || trackingNumber)}`;
}

function hasFulfillableLines(order?: SalesOrderDetail | null): boolean {
    if (!order?.lines?.length) {
        return false;
    }
    return order.lines.some((line) => {
        const ordered = line.qtyOrdered ?? 0;
        const shipped = line.qtyShipped ?? 0;
        return ordered > shipped;
    });
}

export function SupplierOrderDetailPage() {
    const params = useParams<{ soId?: string }>();
    const navigate = useNavigate();
    const orderId = params.soId;
    const { formatDate, formatMoney, formatNumber } = useFormatting();

    const supplierOrderQuery = useSupplierOrder(orderId ?? null, {
        enabled: Boolean(orderId),
    });
    const ackMutation = useAckOrder();
    const createShipmentMutation = useCreateShipment();
    const updateShipmentStatusMutation = useUpdateShipmentStatus();
    const order = supplierOrderQuery.data;
    const acknowledgement = useMemo(() => {
        if (!order?.acknowledgements?.length) {
            return undefined;
        }
        return order.acknowledgements[order.acknowledgements.length - 1];
    }, [order?.acknowledgements]);

    const [shipmentDialogOpen, setShipmentDialogOpen] = useState(false);
    const [declineDialogOpen, setDeclineDialogOpen] = useState(false);
    const [declineReason, setDeclineReason] = useState('');
    const [statusDialog, setStatusDialog] = useState<{
        shipmentId: number;
        status: 'in_transit' | 'delivered';
    } | null>(null);
    const [deliveredAtInput, setDeliveredAtInput] = useState(() =>
        new Date().toISOString().slice(0, 16),
    );

    if (!orderId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Sales order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Sales order missing"
                    description="Provide a sales order identifier to load the supplier workspace view."
                    icon={
                        <AlertTriangle className="h-12 w-12 text-destructive" />
                    }
                    ctaLabel="Back to orders"
                    ctaProps={{
                        onClick: () => navigate('/app/supplier/orders'),
                    }}
                />
            </div>
        );
    }

    if (supplierOrderQuery.isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Sales order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <Skeleton className="h-48 w-full rounded-xl" />
                <Skeleton className="h-96 w-full rounded-xl" />
            </div>
        );
    }

    if (supplierOrderQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Sales order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load sales order"
                    description="We could not retrieve this record. Retry or contact the buyer."
                    icon={
                        <AlertTriangle className="h-12 w-12 text-destructive" />
                    }
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => supplierOrderQuery.refetch() }}
                />
            </div>
        );
    }

    if (!order) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Sales order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Sales order not found"
                    description="This record may have been removed or you no longer have access."
                    icon={
                        <AlertTriangle className="h-12 w-12 text-destructive" />
                    }
                    ctaLabel="Back to supplier orders"
                    ctaProps={{
                        onClick: () => navigate('/app/supplier/orders'),
                    }}
                />
            </div>
        );
    }

    const fulfillmentOrdered =
        order.fulfillment?.orderedQty ??
        order.lines?.reduce((acc, line) => acc + (line.qtyOrdered ?? 0), 0) ??
        0;
    const fulfillmentShipped =
        order.fulfillment?.shippedQty ??
        order.lines?.reduce((acc, line) => acc + (line.qtyShipped ?? 0), 0) ??
        0;
    const fulfillmentPercent =
        fulfillmentOrdered > 0
            ? Math.min(
                  100,
                  Math.round((fulfillmentShipped / fulfillmentOrdered) * 100),
              )
            : 0;
    const canAcknowledge = order.status === 'pending_ack';
    const canCreateShipment =
        hasFulfillableLines(order) &&
        ['accepted', 'partially_fulfilled'].includes(order.status);

    const handleAck = (decision: 'accept' | 'decline') => {
        if (decision === 'decline') {
            setDeclineDialogOpen(true);
            return;
        }
        ackMutation.mutate({ orderId: order.id, decision: 'accept' });
    };

    const handleDeclineConfirm = () => {
        ackMutation.mutate(
            { orderId: order.id, decision: 'decline', reason: declineReason },
            {
                onSuccess: () => {
                    setDeclineDialogOpen(false);
                    setDeclineReason('');
                },
            },
        );
    };

    const handleCreateShipment = async (payload: CreateShipmentPayload) => {
        await createShipmentMutation.mutateAsync({
            orderId: order.id,
            ...payload,
        });
    };

    const handleMarkInTransit = (shipment: SalesOrderShipment) => {
        updateShipmentStatusMutation.mutate({
            shipmentId: shipment.id,
            orderId: order.id,
            status: 'in_transit',
        });
    };

    const handleMarkDelivered = (shipment: SalesOrderShipment) => {
        setDeliveredAtInput(new Date().toISOString().slice(0, 16));
        setStatusDialog({ shipmentId: shipment.id, status: 'delivered' });
    };

    const handleStatusDialogSubmit = () => {
        if (!statusDialog) {
            return;
        }
        updateShipmentStatusMutation.mutate(
            {
                shipmentId: statusDialog.shipmentId,
                orderId: order.id,
                status: statusDialog.status,
                deliveredAt:
                    statusDialog.status === 'delivered'
                        ? deliveredAtInput
                        : undefined,
            },
            {
                onSuccess: () => {
                    setStatusDialog(null);
                },
            },
        );
    };

    const shipments = order.shipments ?? [];

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Sales order {order.soNumber}</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <Card className="border-border/70">
                <CardHeader className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="space-y-1">
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Supplier workspace
                        </p>
                        <CardTitle className="text-3xl font-semibold text-foreground">
                            Sales order {order.soNumber}
                        </CardTitle>
                        <CardDescription>
                            Linked to PO #{order.poId}. Keep the buyer posted on
                            fulfilment and shipment events.
                        </CardDescription>
                        <div className="flex flex-wrap gap-2 pt-2 text-sm text-muted-foreground">
                            <Badge
                                variant="outline"
                                className="border-primary/50 text-primary"
                            >
                                Buyer:{' '}
                                {order.buyerCompanyName ??
                                    `#${order.buyerCompanyId}`}
                            </Badge>
                            <Badge variant="outline">
                                Currency: {order.currency}
                            </Badge>
                            <Badge variant="outline" className="gap-1">
                                <Package className="h-3.5 w-3.5" /> PO #
                                {order.poId}
                            </Badge>
                        </div>
                    </div>
                    <OrderStatusBadge status={order.status} />
                </CardHeader>
                <CardContent className="space-y-6">
                    <dl className="grid gap-4 text-sm md:grid-cols-3">
                        <div>
                            <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                Issue date
                            </dt>
                            <dd className="font-medium text-foreground">
                                {formatDate(order.issueDate)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                Due date
                            </dt>
                            <dd className="font-medium text-foreground">
                                {formatDate(order.dueDate)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                Order total
                            </dt>
                            <dd className="font-medium text-foreground">
                                {formatMoney(
                                    order.totals?.totalMinor !== undefined &&
                                        order.totals?.totalMinor !== null
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
                                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Fulfilment progress
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {formatNumber(fulfillmentShipped, {
                                        maximumFractionDigits: 2,
                                    })}{' '}
                                    of{' '}
                                    {formatNumber(fulfillmentOrdered, {
                                        maximumFractionDigits: 2,
                                    })}{' '}
                                    units shipped
                                </p>
                            </div>
                            <span className="text-lg font-semibold text-foreground">
                                {fulfillmentPercent}%
                            </span>
                        </div>
                        <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-emerald-500 transition-all"
                                style={{ width: `${fulfillmentPercent}%` }}
                            />
                        </div>
                    </div>

                    {canAcknowledge ? (
                        <Alert className="border-amber-200 bg-amber-50">
                            <AlertTitle className="text-amber-900">
                                Awaiting acknowledgement
                            </AlertTitle>
                            <AlertDescription className="text-amber-900/80">
                                Accept or decline this order so the buyer knows
                                if you can fulfil it.
                            </AlertDescription>
                            <div className="mt-4 flex flex-wrap gap-3">
                                <Button
                                    type="button"
                                    onClick={() => handleAck('accept')}
                                    disabled={ackMutation.isPending}
                                >
                                    <CheckCircle2 className="mr-2 h-4 w-4" />{' '}
                                    Accept order
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => handleAck('decline')}
                                    disabled={ackMutation.isPending}
                                >
                                    <Undo2 className="mr-2 h-4 w-4" /> Decline
                                </Button>
                            </div>
                        </Alert>
                    ) : acknowledgement ? (
                        <Alert className="border-muted-foreground/40">
                            <AlertTitle>
                                Order{' '}
                                {acknowledgement.decision === 'accept'
                                    ? 'accepted'
                                    : 'declined'}{' '}
                                on {formatDate(acknowledgement.acknowledgedAt)}
                            </AlertTitle>
                            {acknowledgement.reason ? (
                                <AlertDescription>
                                    {acknowledgement.reason}
                                </AlertDescription>
                            ) : null}
                        </Alert>
                    ) : null}
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle className="text-lg font-semibold">
                            Shipping profile
                        </CardTitle>
                        <CardDescription>
                            Reference the buyer preferences before dispatch.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm">
                            <div>
                                <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Ship to
                                </dt>
                                <dd className="font-medium text-foreground">
                                    {order.shipping?.shipToName ?? '—'}
                                </dd>
                                {order.shipping?.shipToAddress ? (
                                    <p className="text-xs text-muted-foreground">
                                        {order.shipping.shipToAddress}
                                    </p>
                                ) : null}
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Incoterm
                                </dt>
                                <dd className="font-medium text-foreground">
                                    {order.shipping?.incoterm ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Carrier preference
                                </dt>
                                <dd className="font-medium text-foreground">
                                    {order.shipping?.carrierPreference ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                                    Instructions
                                </dt>
                                <dd className="text-sm text-muted-foreground">
                                    {order.shipping?.instructions ?? '—'}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle className="text-lg font-semibold">
                            Linked documents
                        </CardTitle>
                        <CardDescription>
                            Jump over to the connected purchase order if you
                            need revisions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3 text-sm">
                        <Button asChild variant="outline" size="sm">
                            <Link
                                to={`/app/suppliers/pos/${order.poId}`}
                                className="gap-2"
                            >
                                <Package className="h-4 w-4" /> Supplier PO view
                            </Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link
                                to={`/app/purchase-orders/${order.poId}`}
                                className="gap-2"
                            >
                                <ExternalLink className="h-4 w-4" /> Buyer PO
                                detail
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
                    <OrderLinesTable
                        lines={order.lines ?? []}
                        totals={order.totals}
                    />
                </TabsContent>

                <TabsContent value="shipments" className="space-y-4">
                    <Card className="border-border/70">
                        <CardHeader className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <CardTitle className="text-lg font-semibold">
                                    Shipments
                                </CardTitle>
                                <CardDescription>
                                    Log consignments as they leave the dock.
                                </CardDescription>
                            </div>
                            <Button
                                type="button"
                                onClick={() => setShipmentDialogOpen(true)}
                                disabled={
                                    !canCreateShipment ||
                                    createShipmentMutation.isPending
                                }
                            >
                                <PackagePlus className="mr-2 h-4 w-4" /> Create
                                shipment
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {shipments.length === 0 ? (
                                <EmptyState
                                    title="No shipments yet"
                                    description="Capture each dispatch to keep buyers informed and trigger receiving workflows."
                                    icon={
                                        <Truck className="h-10 w-10 text-muted-foreground" />
                                    }
                                />
                            ) : (
                                shipments.map((shipment) => (
                                    <div
                                        key={shipment.id}
                                        className="rounded-lg border border-border/60 p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="font-semibold text-foreground">
                                                    Shipment{' '}
                                                    {shipment.shipmentNo}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {shipment.carrier ??
                                                        'Carrier TBD'}
                                                </p>
                                                {shipment.trackingNumber ? (
                                                    <a
                                                        className="inline-flex items-center gap-1 text-sm text-primary"
                                                        href={
                                                            buildTrackingUrl(
                                                                shipment.carrier,
                                                                shipment.trackingNumber,
                                                            ) ?? undefined
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        {
                                                            shipment.trackingNumber
                                                        }
                                                        <ExternalLink className="h-3.5 w-3.5" />
                                                    </a>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Tracking pending
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-right">
                                                <ShipmentStatusChip
                                                    status={shipment.status}
                                                    className="justify-end"
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Shipped{' '}
                                                    {formatDate(
                                                        shipment.shippedAt,
                                                    )}
                                                </p>
                                                {shipment.deliveredAt ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        Delivered{' '}
                                                        {formatDate(
                                                            shipment.deliveredAt,
                                                        )}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </div>
                                        <Separator className="my-3" />
                                        <div className="grid gap-3 text-sm md:grid-cols-2">
                                            {shipment.lines.map((line) => (
                                                <div
                                                    key={line.soLineId}
                                                    className="flex items-center justify-between"
                                                >
                                                    <span className="text-muted-foreground">
                                                        Line #{line.soLineId}
                                                    </span>
                                                    <span className="font-medium text-foreground">
                                                        {formatNumber(
                                                            line.qtyShipped,
                                                            {
                                                                maximumFractionDigits: 3,
                                                            },
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {shipment.status === 'pending' ? (
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleMarkInTransit(
                                                            shipment,
                                                        )
                                                    }
                                                    disabled={
                                                        updateShipmentStatusMutation.isPending
                                                    }
                                                >
                                                    <Repeat2 className="mr-2 h-4 w-4" />{' '}
                                                    Mark in transit
                                                </Button>
                                            ) : null}
                                            {shipment.status ===
                                            'in_transit' ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleMarkDelivered(
                                                            shipment,
                                                        )
                                                    }
                                                    disabled={
                                                        updateShipmentStatusMutation.isPending
                                                    }
                                                >
                                                    <CheckCircle2 className="mr-2 h-4 w-4" />{' '}
                                                    Mark delivered
                                                </Button>
                                            ) : null}
                                            {shipment.trackingNumber ? (
                                                <Button
                                                    asChild
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <a
                                                        href={
                                                            buildTrackingUrl(
                                                                shipment.carrier,
                                                                shipment.trackingNumber,
                                                            ) ?? undefined
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="gap-2"
                                                    >
                                                        <ExternalLink className="h-4 w-4" />{' '}
                                                        Track package
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="timeline">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-lg font-semibold">
                                Timeline
                            </CardTitle>
                            <CardDescription>
                                Status changes, acknowledgements, and shipment
                                events.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <OrderTimeline
                                entries={order.timeline}
                                isLoading={supplierOrderQuery.isFetching}
                                onRetry={() => supplierOrderQuery.refetch()}
                                emptyLabel="No activity logged yet."
                            />
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <ShipmentCreateDialog
                open={shipmentDialogOpen}
                onOpenChange={setShipmentDialogOpen}
                lines={order.lines ?? []}
                onSubmit={handleCreateShipment}
                isSubmitting={createShipmentMutation.isPending}
            />

            <Dialog
                open={declineDialogOpen}
                onOpenChange={setDeclineDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Decline sales order</DialogTitle>
                        <DialogDescription>
                            Share a short message so the buyer knows why this
                            cannot be fulfilled.
                        </DialogDescription>
                    </DialogHeader>
                    <Textarea
                        rows={4}
                        placeholder="Optional reason"
                        value={declineReason}
                        onChange={(event) =>
                            setDeclineReason(event.target.value)
                        }
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeclineDialogOpen(false)}
                            disabled={ackMutation.isPending}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDeclineConfirm}
                            disabled={ackMutation.isPending}
                        >
                            Decline order
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={Boolean(statusDialog)}
                onOpenChange={(open) => !open && setStatusDialog(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {statusDialog?.status === 'delivered'
                                ? 'Mark shipment delivered'
                                : 'Mark shipment in transit'}
                        </DialogTitle>
                        <DialogDescription>
                            Status updates notify the buyer instantly and keep
                            downstream receiving accurate.
                        </DialogDescription>
                    </DialogHeader>
                    {statusDialog?.status === 'delivered' ? (
                        <div className="space-y-2">
                            <label
                                className="text-xs font-medium text-muted-foreground uppercase"
                                htmlFor="delivered-at-input"
                            >
                                Delivery timestamp
                            </label>
                            <Input
                                id="delivered-at-input"
                                type="datetime-local"
                                value={deliveredAtInput}
                                onChange={(event) =>
                                    setDeliveredAtInput(event.target.value)
                                }
                            />
                        </div>
                    ) : null}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStatusDialog(null)}
                            disabled={updateShipmentStatusMutation.isPending}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={handleStatusDialogSubmit}
                            disabled={updateShipmentStatusMutation.isPending}
                        >
                            {statusDialog?.status === 'delivered'
                                ? 'Confirm delivery'
                                : 'Confirm in transit'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
