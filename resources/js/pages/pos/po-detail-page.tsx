import { useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, FileText, Layers } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { PoHeaderCard } from '@/components/pos/po-header-card';
import { PoLineTable } from '@/components/pos/po-line-table';
import { SendPoDialog, type SendPoDialogPayload } from '@/components/pos/send-po-dialog';
import { PoActivityTimeline } from '@/components/pos/po-activity-timeline';
import { CreateInvoiceDialog } from '@/components/invoices/create-invoice-dialog';
import { EmptyState } from '@/components/empty-state';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { usePo } from '@/hooks/api/pos/use-po';
import { useRecalcPo } from '@/hooks/api/pos/use-recalc-po';
import { useSendPo } from '@/hooks/api/pos/use-send-po';
import { useCancelPo } from '@/hooks/api/pos/use-cancel-po';
import { usePoEvents } from '@/hooks/api/pos/use-events';
import { useCreateInvoice, type CreateInvoiceInput } from '@/hooks/api/invoices/use-create-invoice';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';

export function PoDetailPage() {
    const { formatDate } = useFormatting();
    const params = useParams<{ purchaseOrderId: string }>();
    const navigate = useNavigate();
    const poId = Number(params.purchaseOrderId);
    const [isSendDialogOpen, setSendDialogOpen] = useState(false);
    const [isInvoiceDialogOpen, setInvoiceDialogOpen] = useState(false);

    const poQuery = usePo(poId);
    const recalcMutation = useRecalcPo();
    const sendMutation = useSendPo();
    const cancelMutation = useCancelPo();
    const eventsQuery = usePoEvents(poId);
    const createInvoiceMutation = useCreateInvoice();
    const { hasFeature, notifyPlanLimit, activePersona } = useAuth();
    const isSupplierPersona = activePersona?.type === 'supplier';
    const invoicesEnabled = hasFeature('invoices_enabled');

    if (!Number.isFinite(poId) || poId <= 0) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Purchase Order</title>
                </Helmet>
                <EmptyState
                    title="Invalid purchase order"
                    description="The requested PO id is missing or malformed."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to list"
                    ctaProps={{ onClick: () => navigate('/app/purchase-orders') }}
                />
            </div>
        );
    }

    if (poQuery.isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Purchase Order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <Skeleton className="h-48 w-full rounded-xl" />
                <Skeleton className="h-96 w-full rounded-xl" />
            </div>
        );
    }

    if (poQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Purchase Order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load PO"
                    description="The purchase order could not be retrieved. Please try again or contact support if the issue persists."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => poQuery.refetch() }}
                />
            </div>
        );
    }

    const po = poQuery.data;

    if (!po) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Purchase Order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Purchase order not found"
                    description="This PO was removed or never existed."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="Back to list"
                    ctaProps={{ onClick: () => navigate('/app/purchase-orders') }}
                />
            </div>
        );
    }

    const handleRecalculate = () => recalcMutation.mutate({ poId });

    const handleSend = () => {
        setSendDialogOpen(true);
    };

    const handleSendSubmit = (payload: SendPoDialogPayload) => {
        sendMutation.mutate(
            {
                ...payload,
                poId,
            },
            {
                onSuccess: () => {
                    setSendDialogOpen(false);
                },
            },
        );
    };

    const handleCancel = () => {
        const confirmed = typeof window === 'undefined' ? true : window.confirm('Cancel this purchase order?');
        if (!confirmed) {
            return;
        }

        cancelMutation.mutate({ poId, rfqId: po.rfqId ?? undefined });
    };

    const handleCreateInvoice = () => {
        if (!invoicesEnabled) {
            notifyPlanLimit({ code: 'invoices', message: 'Upgrade required to create invoices.' });
            return;
        }

        setInvoiceDialogOpen(true);
    };

    const handleInvoiceSubmit = (payload: CreateInvoiceInput) => {
        createInvoiceMutation.mutate(payload, {
            onSuccess: (invoice) => {
                setInvoiceDialogOpen(false);
                if (invoice?.id) {
                    navigate(`/app/invoices/${invoice.id}`);
                }
            },
        });
    };

    const canSend = !isSupplierPersona && (po.status === 'draft' || po.status === 'recalculated');
    const canCancel = !isSupplierPersona && (po.status === 'draft' || po.status === 'sent');
    const hasInvoiceableLines = (po.lines ?? []).some((line) => {
        if (typeof line.remainingQuantity === 'number') {
            return line.remainingQuantity > 0;
        }

        const total = typeof line.quantity === 'number' ? line.quantity : 0;
        const invoiced = typeof line.invoicedQuantity === 'number' ? line.invoicedQuantity : 0;
        return total - invoiced > 0;
    });
    const showInvoiceDialog = !isSupplierPersona && invoicesEnabled && (po.lines?.length ?? 0) > 0;
    const timelineError = eventsQuery.isError
        ? eventsQuery.error instanceof Error
            ? eventsQuery.error.message
            : 'Unable to load activity.'
        : null;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>PO #{po.poNumber}</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            {!isSupplierPersona ? (
                <SendPoDialog
                    open={isSendDialogOpen}
                    onOpenChange={setSendDialogOpen}
                    onSubmit={handleSendSubmit}
                    isSubmitting={sendMutation.isPending}
                    supplierName={po.supplierName ?? null}
                    supplierEmail={po.supplierEmail ?? null}
                    latestDelivery={po.latestDelivery ?? null}
                />
            ) : null}

            {showInvoiceDialog ? (
                <CreateInvoiceDialog
                    open={isInvoiceDialogOpen}
                    onOpenChange={setInvoiceDialogOpen}
                    purchaseOrder={po}
                    isSubmitting={createInvoiceMutation.isPending}
                    onSubmit={handleInvoiceSubmit}
                />
            ) : null}

            <PoHeaderCard
                po={po}
                onRecalculate={!isSupplierPersona ? handleRecalculate : undefined}
                onSend={canSend ? handleSend : undefined}
                onCancel={canCancel ? handleCancel : undefined}
                onCreateInvoice={!isSupplierPersona && invoicesEnabled ? handleCreateInvoice : undefined}
                canCreateInvoice={!isSupplierPersona && hasInvoiceableLines}
                isCreatingInvoice={createInvoiceMutation.isPending}
                isRecalculating={recalcMutation.isPending}
                isCancelling={cancelMutation.isPending}
                isSending={sendMutation.isPending}
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="space-y-6">
                    <PoLineTable
                        lines={po.lines ?? []}
                        currency={po.currency}
                        subtotalMinor={po.subtotalMinor}
                        taxMinor={po.taxAmountMinor}
                        totalMinor={po.totalMinor}
                    />

                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">Activity timeline</CardTitle>
                            <CardDescription>Delivery attempts, acknowledgements, and invoice events.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PoActivityTimeline
                                events={eventsQuery.data}
                                isLoading={eventsQuery.isLoading}
                                error={timelineError}
                                onRetry={() => eventsQuery.refetch()}
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">Commercial terms</CardTitle>
                            <CardDescription>Snapshot of tax, incoterms, and source RFQ.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 text-sm">
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Incoterm</dt>
                                    <dd className="font-medium text-foreground">{po.incoterm ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Tax percent</dt>
                                    <dd className="font-medium text-foreground">
                                        {po.taxPercent != null ? `${po.taxPercent}%` : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Source RFQ</dt>
                                    <dd className="flex items-center gap-2">
                                        {po.rfqId ? (
                                            <Button asChild variant="outline" size="sm">
                                                <Link to={`/app/rfqs/${po.rfqId}`}>
                                                    RFQ {po.rfqNumber ?? po.rfqId}
                                                </Link>
                                            </Button>
                                        ) : (
                                            <span className="font-medium text-foreground">—</span>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">Change orders</CardTitle>
                            <CardDescription>Audit of adjustments against this PO.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {po.changeOrders && po.changeOrders.length > 0 ? (
                                <ul className="space-y-3 text-sm">
                                    {po.changeOrders.map((change) => (
                                        <li key={change.id} className="rounded-lg border border-border/60 p-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-semibold text-foreground">{change.reason}</span>
                                                <Badge variant="outline" className="uppercase tracking-wide">
                                                    {change.status}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Proposed {formatDate(change.createdAt)} • Rev {change.poRevisionNo ?? '—'}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-muted-foreground/40 p-6 text-center text-sm text-muted-foreground">
                                    <Layers className="h-8 w-8 text-muted-foreground" />
                                    <span>No change orders recorded yet.</span>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
