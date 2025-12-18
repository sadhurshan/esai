import { useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, CheckCircle2, Handshake, ShieldX } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { AckStatusChip } from '@/components/pos/ack-status-chip';
import { PoLineTable } from '@/components/pos/po-line-table';
import { MoneyCell } from '@/components/quotes/money-cell';
import { usePo } from '@/hooks/api/pos/use-po';
import { useAckPo } from '@/hooks/api/pos/use-ack-po';
import { formatDate } from '@/lib/format';
import { useAuth } from '@/contexts/auth-context';

export function SupplierPoDetailPage() {
    const params = useParams<{ purchaseOrderId?: string }>();
    const navigate = useNavigate();
    const poId = Number(params.purchaseOrderId);
    const { state, hasFeature, activePersona } = useAuth();
    const supplierRole = state.user?.role === 'supplier';
    const isSupplierPersona = activePersona?.type === 'supplier';
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const supplierPortalEnabled = supplierRole || hasFeature('supplier_portal_enabled') || isSupplierPersona;

    const [declineDialogOpen, setDeclineDialogOpen] = useState(false);
    const [declineReason, setDeclineReason] = useState('');
    const poQuery = usePo(poId);
    const ackMutation = useAckPo();

    if (featureFlagsLoaded && !supplierPortalEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier portal</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Supplier portal unavailable"
                    description="Your workspace plan or role does not include supplier acknowledgements yet."
                    icon={<ShieldX className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    if (!Number.isFinite(poId) || poId <= 0) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier purchase order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Invalid purchase order"
                    description="The purchase order identifier is missing or malformed."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to purchase orders"
                    ctaProps={{ onClick: () => navigate('/app/purchase-orders') }}
                />
            </div>
        );
    }

    if (poQuery.isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier purchase order</title>
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
                    <title>Supplier purchase order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load purchase order"
                    description="We could not retrieve this record. Try again or contact the buyer."
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
                    <title>Supplier purchase order</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Purchase order not found"
                    description="This purchase order was removed or no longer available."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    const ackStatus = po.ackStatus ?? 'draft';
    const ackDecisionMade = ackStatus === 'acknowledged' || ackStatus === 'declined';
    const canRespond = po.status === 'sent' && !ackDecisionMade;

    const handleAcknowledge = () => {
        if (!canRespond) {
            return;
        }
        ackMutation.mutate({ poId, decision: 'acknowledged' });
    };

    const handleDeclineConfirm = () => {
        if (!canRespond) {
            return;
        }
        ackMutation.mutate(
            {
                poId,
                decision: 'declined',
                reason: declineReason,
            },
            {
                onSuccess: () => {
                    setDeclineDialogOpen(false);
                    setDeclineReason('');
                },
            },
        );
    };

    const declineDialogDescription =
        'Declining lets the buyer know you cannot fulfill this PO. Share a quick note so they understand next steps.';

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier PO {po.poNumber}</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <Card className="border-border/70">
                <CardHeader className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Supplier portal</p>
                        <CardTitle className="text-2xl font-semibold text-foreground">Purchase order {po.poNumber}</CardTitle>
                        <CardDescription>Review the buyer request and confirm if you can fulfill it.</CardDescription>
                    </div>
                    <AckStatusChip
                        status={ackStatus}
                        sentAt={po.sentAt}
                        acknowledgedAt={po.acknowledgedAt}
                        ackReason={po.ackReason}
                        latestDelivery={po.latestDelivery ?? null}
                    />
                </CardHeader>
                <CardContent className="space-y-6">
                    <dl className="grid gap-4 md:grid-cols-3 text-sm">
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Issued on</dt>
                            <dd className="font-medium text-foreground">{formatDate(po.createdAt)}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Revision</dt>
                            <dd className="font-medium text-foreground">Rev {po.revisionNo}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Total value</dt>
                            <dd className="font-medium text-foreground">
                                <MoneyCell amountMinor={po.totalMinor} currency={po.currency} label="PO total" />
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Incoterm</dt>
                            <dd className="font-medium text-foreground">{po.incoterm ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Tax %</dt>
                            <dd className="font-medium text-foreground">{po.taxPercent ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs uppercase tracking-wide text-muted-foreground">Status</dt>
                            <dd className="font-medium capitalize text-foreground">{po.status}</dd>
                        </div>
                    </dl>
                    {po.ackReason ? (
                        <div className="rounded-lg border border-dashed border-muted-foreground/40 bg-muted/40 p-4 text-sm text-muted-foreground">
                            Supplier note: {po.ackReason}
                        </div>
                    ) : null}
                    <div className="flex flex-wrap gap-3">
                        <Button onClick={handleAcknowledge} disabled={!canRespond || ackMutation.isPending}>
                            <CheckCircle2 className="mr-2 h-4 w-4" /> Acknowledge PO
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeclineDialogOpen(true)}
                            disabled={!canRespond || ackMutation.isPending}
                        >
                            <Handshake className="mr-2 h-4 w-4" /> Decline
                        </Button>
                        {!canRespond ? (
                            <p className="text-xs text-muted-foreground">
                                {ackDecisionMade
                                    ? 'Decision already captured for this order.'
                                    : 'Only sent purchase orders can be acknowledged.'}
                            </p>
                        ) : null}
                    </div>
                </CardContent>
            </Card>

            <PoLineTable
                lines={po.lines ?? []}
                currency={po.currency}
                subtotalMinor={po.subtotalMinor}
                taxMinor={po.taxAmountMinor}
                totalMinor={po.totalMinor}
            />

            <Dialog open={declineDialogOpen} onOpenChange={setDeclineDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Decline purchase order</DialogTitle>
                        <DialogDescription>{declineDialogDescription}</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="decline-reason">Reason (optional)</Label>
                        <Textarea
                            id="decline-reason"
                            placeholder="Share why the PO cannot be fulfilled"
                            value={declineReason}
                            onChange={(event) => setDeclineReason(event.target.value)}
                            rows={4}
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeclineDialogOpen(false)} disabled={ackMutation.isPending}>
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" onClick={handleDeclineConfirm} disabled={ackMutation.isPending}>
                            Decline PO
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
