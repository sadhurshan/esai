import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, Download, Wallet } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { MoneyCell } from '@/components/quotes/money-cell';
import { InvoiceReviewTimeline, buildInvoiceReviewTimeline } from '@/components/invoices/invoice-review-timeline';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useSupplierInvoice } from '@/hooks/api/invoices/use-supplier-invoice';
import { useSubmitSupplierInvoice } from '@/hooks/api/invoices/use-submit-supplier-invoice';
import { usePoEvents } from '@/hooks/api/pos/use-events';
import type { DocumentAttachment, InvoiceDetail } from '@/types/sourcing';

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'secondary',
    submitted: 'outline',
    buyer_review: 'outline',
    approved: 'default',
    paid: 'default',
    rejected: 'destructive',
};

export function SupplierInvoiceDetailPage() {
    const params = useParams<{ invoiceId?: string }>();
    const invoiceId = params.invoiceId ?? '';
    const navigate = useNavigate();
    const { state, hasFeature, activePersona } = useAuth();
    const { formatDate } = useFormatting();
    const [resubmitDialogOpen, setResubmitDialogOpen] = useState(false);
    const [resubmitNote, setResubmitNote] = useState('');
    const submitMutation = useSubmitSupplierInvoice();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const supplierRole = state.user?.role === 'supplier';
    const isSupplierPersona = activePersona?.type === 'supplier';
    const supplierPortalEligible = supplierRole || isSupplierPersona || hasFeature('supplier_portal_enabled');
    const supplierInvoicingEnabled = supplierPortalEligible && hasFeature('supplier_invoicing_enabled');
    const shouldLoadInvoice = Boolean(invoiceId) && (!featureFlagsLoaded || supplierInvoicingEnabled);

    const invoiceQuery = useSupplierInvoice(shouldLoadInvoice ? invoiceId : undefined);
    const purchaseOrderId = Number(invoiceQuery.data?.purchaseOrderId ?? 0);
    const poEventsQuery = usePoEvents(purchaseOrderId);

    if (!invoiceId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Invoice not found"
                    description="The requested invoice identifier was missing."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to invoices"
                    ctaProps={{ onClick: () => navigate('/app/supplier/invoices') }}
                />
            </div>
        );
    }

    if (featureFlagsLoaded && !supplierInvoicingEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Supplier portal unavailable"
                    description="This workspace plan does not include supplier-authored invoicing. Request an upgrade to continue."
                    icon={<Wallet className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    if (invoiceQuery.isLoading) {
        return <SupplierInvoiceDetailSkeleton />;
    }

    if (invoiceQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load invoice"
                    description="Please retry in a few seconds or contact the buyer if the issue persists."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => invoiceQuery.refetch() }}
                />
            </div>
        );
    }

    const invoice = invoiceQuery.data;

    if (!invoice) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Invoice missing"
                    description="This invoice was removed or is no longer available."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to invoices"
                    ctaProps={{ onClick: () => navigate('/app/supplier/invoices') }}
                />
            </div>
        );
    }

    const statusVariant = STATUS_VARIANTS[invoice.status] ?? 'outline';
    const attachments = gatherAttachments(invoice);
    const supplierSubmitted = invoice.createdByType === 'supplier' || Boolean(invoice.supplierCompanyId);
    const canEdit = invoice.status === 'draft';
    const canResubmit = invoice.status === 'rejected';
    const poEvents = poEventsQuery.data ?? [];
    const reviewTimeline = useMemo(
        () => buildInvoiceReviewTimeline(invoice, { formatDate, events: poEvents }),
        [invoice, formatDate, poEvents],
    );
    const handleResubmit = () => {
        if (!invoice) {
            return;
        }

        submitMutation.mutate(
            { invoiceId: invoice.id, note: resubmitNote.trim() || undefined },
            {
                onSuccess: () => {
                    setResubmitDialogOpen(false);
                    setResubmitNote('');
                },
            },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Invoice {invoice.invoiceNumber}</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Supplier portal</p>
                    <h1 className="text-3xl font-semibold text-foreground">Invoice {invoice.invoiceNumber}</h1>
                    <p className="text-sm text-muted-foreground">
                        Linked to purchase order{' '}
                        {invoice.purchaseOrder ? (
                            <Link className="text-primary" to={`/app/suppliers/pos/${invoice.purchaseOrder.id}`}>
                                {invoice.purchaseOrder.poNumber ?? `PO-${invoice.purchaseOrder.id}`}
                            </Link>
                        ) : (
                            `PO-${invoice.purchaseOrderId}`
                        )}
                        .
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {supplierSubmitted ? (
                            <Badge variant="outline" className="border-amber-200 bg-amber-50 text-amber-800">
                                Supplier submission
                            </Badge>
                        ) : null}
                        <Badge variant={statusVariant} className="rounded-full px-4 capitalize">
                            {formatInvoiceStatus(invoice.status)}
                        </Badge>
                    </div>
                </div>
                <div className="flex flex-col gap-2">
                    <div className="flex flex-wrap gap-2">
                        {canEdit ? (
                            <Button size="sm" asChild>
                                <Link to={`/app/supplier/invoices/${invoice.id}/edit`}>Edit draft</Link>
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" disabled>
                                Edit draft
                            </Button>
                        )}
                        {canResubmit ? (
                            <Button size="sm" onClick={() => setResubmitDialogOpen(true)} disabled={submitMutation.isPending}>
                                {submitMutation.isPending ? 'Resubmitting…' : 'Resubmit invoice'}
                            </Button>
                        ) : null}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {canResubmit
                            ? 'Add a short note so your buyer knows what changed before their next review.'
                            : 'Edit actions are only available while the invoice is in draft.'}
                    </p>
                </div>
            </div>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Invoice summary</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <div className="grid gap-4 md:grid-cols-3">
                        <MetadataItem label="Buyer company" value={invoice.companyId ? `Company #${invoice.companyId}` : '—'} />
                        <MetadataItem label="Invoice date" value={formatDate(invoice.invoiceDate)} />
                        <MetadataItem label="Due date" value={formatDate(invoice.dueDate)} />
                        <MetadataItem label="Created" value={formatDate(invoice.createdAt)} />
                        <MetadataItem label="Submitted" value={formatDate(invoice.submittedAt ?? invoice.createdAt)} />
                        <MetadataItem label="Last reviewed" value={formatDate(invoice.reviewedAt)} />
                        <MetadataItem label="Currency" value={invoice.currency} />
                        <MetadataItem label="Payment reference" value={invoice.paymentReference ?? '—'} />
                        <MetadataItem label="Status" value={formatInvoiceStatus(invoice.status)} />
                    </div>
                    <Separator />
                    <div className="grid gap-4 md:grid-cols-3">
                        <MoneyCell amountMinor={invoice.subtotalMinor} currency={invoice.currency} label="Subtotal" className="text-base" />
                        <MoneyCell amountMinor={invoice.taxAmountMinor} currency={invoice.currency} label="Tax" className="text-base" />
                        <MoneyCell amountMinor={invoice.totalMinor} currency={invoice.currency} label="Total" className="text-base" />
                    </div>
                </CardContent>
            </Card>

            {invoice.reviewNote ? (
                <Card className="border-amber-300 bg-amber-50/80">
                    <CardHeader>
                        <CardTitle className="text-amber-900">Buyer feedback</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-amber-900">{invoice.reviewNote}</p>
                        <p className="mt-2 text-xs text-amber-800">
                            Updated {formatDate(invoice.reviewedAt)} by {invoice.reviewedBy?.name ?? 'Accounts Payable'}
                        </p>
                    </CardContent>
                </Card>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2 border-border/70">
                    <CardHeader>
                        <CardTitle>Line items</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <th className="py-2 pr-4">Description</th>
                                    <th className="py-2 pr-4">PO line</th>
                                    <th className="py-2 pr-4 text-right">Qty</th>
                                    <th className="py-2 pr-4 text-right">Unit price</th>
                                    <th className="py-2 pr-4 text-right">Extended</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoice.lines.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="py-6 text-center text-muted-foreground">
                                            No invoice lines recorded yet.
                                        </td>
                                    </tr>
                                ) : (
                                    invoice.lines.map((line) => {
                                        const extended = (line.unitPriceMinor ?? Math.round(line.unitPrice * 100)) * line.quantity;
                                        return (
                                            <tr key={line.id} className="border-t border-border/60">
                                                <td className="py-3 pr-4 font-medium text-foreground">{line.description}</td>
                                                <td className="py-3 pr-4 text-muted-foreground">#{line.poLineId}</td>
                                                <td className="py-3 pr-4 text-right">
                                                    {line.quantity.toLocaleString(undefined, { maximumFractionDigits: 3 })}
                                                </td>
                                                <td className="py-3 pr-4 text-right">
                                                    <MoneyCell amountMinor={line.unitPriceMinor ?? Math.round(line.unitPrice * 100)} currency={invoice.currency} label="Unit price" />
                                                </td>
                                                <td className="py-3 pr-4 text-right">
                                                    <MoneyCell amountMinor={extended} currency={invoice.currency} label="Line total" />
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Review timeline</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InvoiceReviewTimeline entries={reviewTimeline} emptyLabel="No review activity yet." />
                    </CardContent>
                </Card>
            </div>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Attachments</CardTitle>
                </CardHeader>
                <CardContent>
                    {attachments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No supporting documents uploaded yet.</p>
                    ) : (
                        <ul className="divide-y divide-border/60 text-sm">
                            {attachments.map((attachment) => (
                                <li key={`${attachment.id}-${attachment.filename}`} className="flex items-center justify-between py-3">
                                    <div>
                                        <p className="font-medium text-foreground">{attachment.filename}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {attachment.mime ?? 'application/pdf'} · {formatFileSize(attachment.sizeBytes)}
                                        </p>
                                    </div>
                                    <Button variant="outline" size="sm" disabled>
                                        <Download className="mr-2 h-4 w-4" /> Download
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="mt-3 text-xs text-muted-foreground">Attachment management will be enabled during the edit wizard.</p>
                </CardContent>
            </Card>

            <Dialog
                open={resubmitDialogOpen}
                onOpenChange={(open) => {
                    setResubmitDialogOpen(open);
                    if (!open) {
                        setResubmitNote('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Resubmit invoice {invoice.invoiceNumber}</DialogTitle>
                        <DialogDescription>
                            Explain what you updated so accounts payable can review the changes quickly.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="resubmit-note">Message to buyer (optional)</Label>
                        <Textarea
                            id="resubmit-note"
                            rows={4}
                            value={resubmitNote}
                            onChange={(event) => setResubmitNote(event.target.value)}
                            placeholder="Summarize adjustments, replacements, or new attachments."
                        />
                    </div>
                    {invoice.reviewNote ? (
                        <p className="text-xs text-muted-foreground">
                            Last buyer note: {invoice.reviewNote}
                        </p>
                    ) : null}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setResubmitDialogOpen(false)} disabled={submitMutation.isPending}>
                            Cancel
                        </Button>
                        <Button onClick={handleResubmit} disabled={submitMutation.isPending}>
                            {submitMutation.isPending ? 'Sending…' : 'Send to buyer'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function formatInvoiceStatus(status?: string | null): string {
    if (!status) {
        return 'unknown';
    }

    return status.replace(/_/g, ' ');
}

function gatherAttachments(invoice: InvoiceDetail): DocumentAttachment[] {
    const attachments: DocumentAttachment[] = [];
    if (invoice.document) {
        attachments.push(invoice.document);
    }
    if (invoice.attachments && invoice.attachments.length > 0) {
        attachments.push(...invoice.attachments);
    }
    return attachments;
}

function formatFileSize(bytes?: number | null): string {
    if (!bytes || bytes <= 0) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toFixed(1)} ${units[unitIndex]}`;
}

function MetadataItem({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-sm font-semibold text-foreground">{value ?? '—'}</p>
        </div>
    );
}

function SupplierInvoiceDetailSkeleton() {
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier invoices</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />
            <Skeleton className="h-10 w-1/4" />
            <Skeleton className="h-48 w-full" />
            <Skeleton className="h-32 w-full" />
            <Skeleton className="h-64 w-full" />
        </div>
    );
}
