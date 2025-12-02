import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, Download, Wallet } from 'lucide-react';

import { ExportButtons } from '@/components/downloads/export-buttons';
import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { MoneyCell } from '@/components/quotes/money-cell';
import { FileDropzone } from '@/components/file-dropzone';
import { useAuth } from '@/contexts/auth-context';
import { useInvoice } from '@/hooks/api/invoices/use-invoice';
import { useAttachInvoiceFile } from '@/hooks/api/invoices/use-attach-invoice-file';
import type { DocumentAttachment, InvoiceDetail } from '@/types/sourcing';
import { useFormatting } from '@/contexts/formatting-context';

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'secondary',
    submitted: 'outline',
    posted: 'outline',
    approved: 'default',
    paid: 'default',
    overdue: 'destructive',
    disputed: 'destructive',
    rejected: 'destructive',
};

export function InvoiceDetailPage() {
    const { formatDate, formatNumber } = useFormatting();
    const params = useParams<{ invoiceId?: string }>();
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const invoicesEnabled = hasFeature('invoices_enabled');
    const invoiceId = params.invoiceId ?? '';
    const invoiceQuery = useInvoice(invoiceId);
    const attachMutation = useAttachInvoiceFile();

    if (featureFlagsLoaded && !invoicesEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Invoices unavailable"
                    description="Upgrade your plan to review invoices and file attachments."
                    icon={<Wallet className="h-10 w-10" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    if (!invoiceId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Invoice not found"
                    description="The requested invoice identifier was missing."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to invoices"
                    ctaProps={{ onClick: () => navigate('/app/invoices') }}
                />
            </div>
        );
    }

    if (invoiceQuery.isLoading) {
        return <InvoiceDetailSkeleton />;
    }

    if (invoiceQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load invoice"
                    description="Please retry or contact your administrator if the issue persists."
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
                    <title>Invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Invoice missing"
                    description="This invoice was removed or is no longer accessible."
                    icon={<AlertTriangle className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to invoices"
                    ctaProps={{ onClick: () => navigate('/app/invoices') }}
                />
            </div>
        );
    }

    const statusVariant = STATUS_VARIANTS[invoice.status] ?? 'outline';
    const attachments = gatherAttachments(invoice);

    const handleAttachmentUpload = (files: File[]) => {
        if (!invoice || files.length === 0) {
            return;
        }

        attachMutation.mutate({
            invoiceId: invoice.id,
            file: files[0],
        });
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
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Finance</p>
                    <h1 className="text-3xl font-semibold text-foreground">Invoice {invoice.invoiceNumber}</h1>
                    <p className="text-sm text-muted-foreground">
                        Linked to purchase order {invoice.purchaseOrder?.poNumber ?? `#${invoice.purchaseOrderId}`}.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {invoice.purchaseOrderId ? (
                        <Button asChild variant="outline">
                            <Link to={`/app/purchase-orders/${invoice.purchaseOrderId}`}>View purchase order</Link>
                        </Button>
                    ) : null}
                    <ExportButtons
                        documentType="invoice"
                        documentId={invoice.id}
                        reference={invoice.invoiceNumber ? `Invoice ${invoice.invoiceNumber}` : undefined}
                        size="sm"
                    />
                    <Badge variant={statusVariant} className="h-9 rounded-full px-4 text-base capitalize">
                        {invoice.status}
                    </Badge>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2 border-border/70">
                    <CardHeader>
                        <CardTitle>Invoice summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="grid gap-4 md:grid-cols-3">
                            <MetadataItem label="Supplier" value={invoice.supplier?.name ?? '—'} />
                            <MetadataItem label="Invoice date" value={formatDate(invoice.invoiceDate)} />
                            <MetadataItem label="Created" value={formatDate(invoice.createdAt)} />
                            <MetadataItem label="Currency" value={invoice.currency} />
                            <MetadataItem label="PO #" value={invoice.purchaseOrder?.poNumber ?? '—'} />
                            <MetadataItem label="Status" value={invoice.status} />
                        </div>
                        <Separator />
                        <div className="grid gap-4 md:grid-cols-3">
                            <MoneyCell
                                amountMinor={invoice.subtotalMinor}
                                currency={invoice.currency}
                                label="Subtotal"
                                className="text-base"
                            />
                            <MoneyCell
                                amountMinor={invoice.taxAmountMinor}
                                currency={invoice.currency}
                                label="Tax"
                                className="text-base"
                            />
                            <MoneyCell
                                amountMinor={invoice.totalMinor}
                                currency={invoice.currency}
                                label="Total"
                                className="text-base"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Matching summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {invoice.matchSummary ? (
                            <dl className="space-y-2 text-sm">
                                {Object.entries(invoice.matchSummary).map(([key, value]) => (
                                    <div key={key} className="flex items-center justify-between">
                                        <dt className="text-muted-foreground capitalize">{key.replace(/_/g, ' ')}</dt>
                                        <dd className="font-semibold text-foreground">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        ) : (
                            <p className="text-sm text-muted-foreground">No matching data reported yet.</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="border-border/70">
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
                                    const extended = (line.unitPriceMinor ?? 0) * line.quantity;
                                    return (
                                        <tr key={line.id} className="border-t border-border/60">
                                            <td className="py-3 pr-4 font-medium text-foreground">{line.description}</td>
                                            <td className="py-3 pr-4 text-muted-foreground">#{line.poLineId}</td>
                                            <td className="py-3 pr-4 text-right">
                                                {formatNumber(line.quantity, { maximumFractionDigits: 3 })}
                                            </td>
                                            <td className="py-3 pr-4 text-right">
                                                <MoneyCell
                                                    amountMinor={line.unitPriceMinor ?? Math.round(line.unitPrice * 100)}
                                                    currency={invoice.currency}
                                                    label="Unit price"
                                                />
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
                    <CardTitle>Attachments</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    {attachments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No invoice documents uploaded yet.</p>
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

                    <FileDropzone
                        label="Upload invoice PDF"
                        description={attachMutation.isPending ? 'Uploading…' : 'PDF files up to 50 MB.'}
                        accept={['application/pdf']}
                        disabled={attachMutation.isPending}
                        onFilesSelected={handleAttachmentUpload}
                    />
                </CardContent>
            </Card>
        </div>
    );
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

function InvoiceDetailSkeleton() {
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Invoices</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />
            <div className="space-y-4">
                <Skeleton className="h-8 w-2/5" />
                <Skeleton className="h-40 w-full" />
                <Skeleton className="h-64 w-full" />
                <Skeleton className="h-48 w-full" />
            </div>
        </div>
    );
}
