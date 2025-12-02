import { useEffect, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, Download, PackageOpen, PackageSearch } from 'lucide-react';

import { ExportButtons } from '@/components/downloads/export-buttons';
import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { GrnStatusBadge } from '@/components/receiving/grn-status-badge';
import { FileDropzone } from '@/components/file-dropzone';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useGrn } from '@/hooks/api/receiving/use-grn';
import { useAttachGrnFile } from '@/hooks/api/receiving/use-attach-grn-file';
import { useCloseNcr } from '@/hooks/api/receiving/use-close-ncr';
import { useCreateNcr } from '@/hooks/api/receiving/use-create-ncr';
import type { DocumentAttachment, GrnLine, NcrDisposition } from '@/types/sourcing';

export function ReceivingDetailPage() {
    const { formatDate } = useFormatting();
    const { grnId: grnIdParam } = useParams<{ grnId?: string }>();
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const receivingEnabled = hasFeature('inventory_enabled');

    const grnId = Number(grnIdParam);
    const grnQuery = useGrn(Number.isFinite(grnId) ? grnId : 0);
    const attachMutation = useAttachGrnFile();
    const createNcrMutation = useCreateNcr();
    const closeNcrMutation = useCloseNcr();
    const [isNcrDialogOpen, setIsNcrDialogOpen] = useState(false);
    const [selectedLine, setSelectedLine] = useState<GrnLine | null>(null);
    const [closingNcrId, setClosingNcrId] = useState<number | null>(null);

    if (featureFlagsLoaded && !receivingEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Receiving</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Receiving unavailable"
                    description="Upgrade your Elements Supply plan to view and manage GRNs."
                    icon={<PackageOpen className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    if (!grnIdParam || !Number.isFinite(grnId) || grnId <= 0) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Receiving</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="GRN not found"
                    description="The goods receipt identifier was missing or invalid."
                    icon={<AlertTriangle className="h-12 w-12 text-destructive" />}
                    ctaLabel="Back to receiving"
                    ctaProps={{ onClick: () => navigate('/app/receiving') }}
                />
            </div>
        );
    }

    if (grnQuery.isLoading) {
        return <ReceivingDetailSkeleton />;
    }

    if (grnQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Receiving</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load GRN"
                    description="Please retry or contact support if the issue continues."
                    icon={<AlertTriangle className="h-12 w-12 text-destructive" />}
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => grnQuery.refetch() }}
                />
            </div>
        );
    }

    const grn = grnQuery.data;
    if (!grn) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Receiving</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="GRN missing"
                    description="This goods receipt was removed or is no longer accessible."
                    icon={<PackageSearch className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="Back to receiving"
                    ctaProps={{ onClick: () => navigate('/app/receiving') }}
                />
            </div>
        );
    }

    const attachments: DocumentAttachment[] = grn.attachments ?? [];

    const handleRaiseNcr = (line: GrnLine) => {
        setSelectedLine(line);
        setIsNcrDialogOpen(true);
    };

    const handleNcrDialogChange = (open: boolean) => {
        setIsNcrDialogOpen(open);
        if (!open) {
            setSelectedLine(null);
        }
    };

    const handleSubmitNcr = (values: { reason: string; disposition?: 'rework' | 'return' | 'accept_as_is' }) => {
        if (!selectedLine) {
            return;
        }

        createNcrMutation.mutate(
            {
                grnId: grn.id,
                poLineId: selectedLine.poLineId,
                reason: values.reason,
                disposition: values.disposition,
            },
            {
                onSuccess: () => {
                    setIsNcrDialogOpen(false);
                    setSelectedLine(null);
                },
            },
        );
    };

    const handleCloseNcr = (ncrId: number) => {
        setClosingNcrId(ncrId);
        closeNcrMutation.mutate(
            { grnId: grn.id, ncrId },
            {
                onSettled: () => setClosingNcrId(null),
            },
        );
    };

    const linesWithNcrs = grn.lines.filter((line) => (line.ncrs?.length ?? 0) > 0 || line.ncrFlag).length;
    const ncrSummary = {
        open: grn.ncrSummary?.open ?? 0,
        total: grn.ncrSummary?.total ?? 0,
        flaggedLines: linesWithNcrs,
    };

    const handleUpload = (files: File[]) => {
        if (!grn || files.length === 0) {
            return;
        }
        const [file] = files;
        attachMutation.mutate({
            grnId: grn.id,
            file,
            filename: file.name,
        });
    };

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>GRN {grn.grnNumber}</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Operations</p>
                    <h1 className="text-3xl font-semibold text-foreground">GRN {grn.grnNumber}</h1>
                    <p className="text-sm text-muted-foreground">
                        Linked to purchase order{' '}
                        {grn.purchaseOrderNumber ? (
                            <Link className="text-primary" to={`/app/purchase-orders/${grn.purchaseOrderId}`}>
                                {grn.purchaseOrderNumber}
                            </Link>
                        ) : (
                            `#${grn.purchaseOrderId}`
                        )}
                        .
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link to="/app/receiving">Back to list</Link>
                    </Button>
                    {grn.purchaseOrderId ? (
                        <Button asChild variant="outline">
                            <Link to={`/app/purchase-orders/${grn.purchaseOrderId}`}>View PO</Link>
                        </Button>
                    ) : null}
                    <ExportButtons
                        documentType="grn"
                        documentId={grn.id}
                        reference={grn.grnNumber ? `GRN ${grn.grnNumber}` : undefined}
                        size="sm"
                    />
                    <GrnStatusBadge status={grn.status} />
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2 border-border/70">
                    <CardHeader>
                        <CardTitle>Receipt summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="grid gap-4 md:grid-cols-3">
                            <MetadataItem label="Supplier" value={grn.supplierName ?? '—'} />
                            <MetadataItem label="Received" value={formatDate(grn.receivedAt ?? grn.postedAt)} />
                            <MetadataItem label="Reference" value={grn.reference ?? '—'} />
                            <MetadataItem label="Status" value={grn.status} />
                            <MetadataItem label="Created by" value={grn.createdBy?.name ?? '—'} />
                            <MetadataItem label="Notes" value={grn.notes ?? '—'} />
                        </div>
                        <Separator />
                        <div className="grid gap-4 md:grid-cols-3">
                            <MetadataItem label="Lines" value={grn.lines.length} />
                            <MetadataItem label="Attachments" value={attachments.length} />
                            <MetadataItem label="Timeline events" value={grn.timeline?.length ?? 0} />
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-border/70">
                    <CardHeader>
                        <CardTitle>Status progression</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {grn.timeline && grn.timeline.length > 0 ? (
                            <ol className="space-y-3 text-sm">
                                {grn.timeline.map((event) => (
                                    <li key={event.id} className="rounded-lg border border-border/50 p-3">
                                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                                            <span>{formatDate(event.occurredAt)}</span>
                                            <span>{event.actor?.name ?? 'System'}</span>
                                        </div>
                                        <p className="mt-1 font-medium text-foreground">{event.summary}</p>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="text-sm text-muted-foreground">No timeline entries captured yet.</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Quality overview</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    {ncrSummary.total === 0 ? (
                        <p className="text-muted-foreground">No NCRs recorded for this receipt.</p>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <QualityMetric label="Open NCRs" value={ncrSummary.open} highlight={ncrSummary.open > 0} />
                            <QualityMetric label="Total NCRs" value={ncrSummary.total} />
                            <QualityMetric label="Lines flagged" value={ncrSummary.flaggedLines} />
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>GRN lines</CardTitle>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th className="py-2 pr-4">Line</th>
                                <th className="py-2 pr-4">Ordered</th>
                                <th className="py-2 pr-4">Received to date</th>
                                <th className="py-2 pr-4">This GRN</th>
                                <th className="py-2 pr-4">UoM</th>
                                <th className="py-2 pr-4">Variance</th>
                                <th className="py-2 pr-4">Quality</th>
                            </tr>
                        </thead>
                        <tbody>
                            {grn.lines.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="py-6 text-center text-muted-foreground">
                                        No lines recorded on this GRN.
                                    </td>
                                </tr>
                            ) : (
                                grn.lines.map((line) => (
                                    <GrnLineRow
                                        key={`${line.poLineId}-${line.id ?? line.poLineId}`}
                                        line={line}
                                        onRaiseNcr={handleRaiseNcr}
                                        onCloseNcr={handleCloseNcr}
                                        closingNcrId={closingNcrId}
                                        isCreatingNcr={createNcrMutation.isPending}
                                    />
                                ))
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
                        <p className="text-sm text-muted-foreground">No documents uploaded yet.</p>
                    ) : (
                        <ul className="divide-y divide-border/60 text-sm">
                            {attachments.map((attachment) => (
                                <li key={`${attachment.id}-${attachment.filename}`} className="flex items-center justify-between py-3">
                                    <div>
                                        <p className="font-medium text-foreground">{attachment.filename}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {attachment.mime ?? 'application/octet-stream'} · {formatFileSize(attachment.sizeBytes)}
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
                                label="Upload packing slip or photo"
                                description={attachMutation.isPending ? 'Uploading…' : 'PDF or image files up to 25 MB.'}
                                accept={['application/pdf', 'image/*']}
                                disabled={attachMutation.isPending}
                                onFilesSelected={handleUpload}
                            />
                        </CardContent>
                    </Card>

                    <RaiseNcrDialog
                        open={isNcrDialogOpen}
                        line={selectedLine}
                        onOpenChange={handleNcrDialogChange}
                        isSubmitting={createNcrMutation.isPending}
                        onSubmit={handleSubmitNcr}
                    />
        </div>
    );
}

function MetadataItem({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-sm font-semibold text-foreground">{value ?? '—'}</p>
        </div>
    );
}

function GrnLineRow({
    line,
    onRaiseNcr,
    onCloseNcr,
    closingNcrId,
    isCreatingNcr,
}: {
    line: GrnLine;
    onRaiseNcr: (line: GrnLine) => void;
    onCloseNcr: (ncrId: number) => void;
    closingNcrId: number | null;
    isCreatingNcr: boolean;
}) {
    const { formatNumber } = useFormatting();
    const varianceVariant = line.variance === 'over' ? 'destructive' : line.variance === 'short' ? 'secondary' : 'outline';
    const ncrs = line.ncrs ?? [];
    const isFlagged = Boolean(line.ncrFlag);

    return (
        <tr className="border-t border-border/60">
            <td className="py-3 pr-4">
                <p className="font-medium text-foreground">{line.description ?? `PO line #${line.poLineId}`}</p>
                <p className="text-xs text-muted-foreground">Line {line.lineNo ?? line.poLineId}</p>
            </td>
            <td className="py-3 pr-4">{formatQuantity(line.orderedQty, line.uom, formatNumber)}</td>
            <td className="py-3 pr-4">{formatQuantity(line.previouslyReceived, line.uom, formatNumber)}</td>
            <td className="py-3 pr-4 font-semibold text-foreground">{formatQuantity(line.qtyReceived, line.uom, formatNumber)}</td>
            <td className="py-3 pr-4">{line.uom ?? '—'}</td>
            <td className="py-3 pr-4">
                {line.variance ? (
                    <Badge variant={varianceVariant} className="capitalize">
                        {line.variance}
                    </Badge>
                ) : (
                    <span className="text-xs text-muted-foreground">Balanced</span>
                )}
            </td>
            <td className="py-3 pr-4">
                <div className="space-y-2">
                    {ncrs.length === 0 ? (
                        <p className="text-xs text-muted-foreground">{isFlagged ? 'Flagged for review' : 'No NCRs'}</p>
                    ) : (
                        <div className="space-y-2">
                            {ncrs.map((ncr) => (
                                <div key={ncr.id} className="flex items-center justify-between rounded border border-border/60 px-2 py-1 text-xs">
                                    <div>
                                        <span className={`font-semibold ${ncr.status === 'open' ? 'text-destructive' : 'text-muted-foreground'}`}>
                                            NCR #{ncr.id}
                                        </span>
                                        <span className="ml-2 capitalize text-muted-foreground">{ncr.status}</span>
                                    </div>
                                    {ncr.status === 'open' ? (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="h-7 px-2 text-xs"
                                            onClick={() => onCloseNcr(ncr.id)}
                                            disabled={closingNcrId === ncr.id}
                                        >
                                            {closingNcrId === ncr.id ? 'Closing…' : 'Close'}
                                        </Button>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    )}
                    <Button size="sm" className="h-7 px-2 text-xs" variant="outline" onClick={() => onRaiseNcr(line)} disabled={isCreatingNcr}>
                        Raise NCR
                    </Button>
                </div>
            </td>
        </tr>
    );
}

function formatQuantity(value: number | null | undefined, uom: string | null | undefined, formatNumber: (val: number, options?: Intl.NumberFormatOptions & { fallback?: string }) => string): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }
    const formatted = formatNumber(value, { maximumFractionDigits: Math.abs(value) >= 1 ? 2 : 3 });
    return `${formatted} ${uom ?? ''}`.trim();
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

function RaiseNcrDialog({
    open,
    line,
    onOpenChange,
    isSubmitting,
    onSubmit,
}: {
    open: boolean;
    line: GrnLine | null;
    onOpenChange: (open: boolean) => void;
    isSubmitting: boolean;
    onSubmit: (values: { reason: string; disposition?: NcrDisposition }) => void;
}) {
    const [reason, setReason] = useState('');
    const [disposition, setDisposition] = useState<NcrDisposition | undefined>();

    useEffect(() => {
        if (open) {
            setReason('');
            setDisposition(undefined);
        }
    }, [open, line?.poLineId]);

    const disableSubmit = !reason.trim() || !line;

    const handleSubmit = () => {
        if (disableSubmit) {
            return;
        }
        onSubmit({ reason: reason.trim(), disposition });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Raise non-conformance</DialogTitle>
                    <DialogDescription>
                        {line ? `Line ${line.lineNo ?? line.poLineId} · ${line.description ?? 'PO line'}` : 'Select a line to continue.'}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="ncr-reason">Reason</Label>
                        <Textarea
                            id="ncr-reason"
                            placeholder="Describe the non-conformance"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            rows={4}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Disposition</Label>
                        <Select
                            value={disposition ?? 'none'}
                            onValueChange={(value) => setDisposition(value === 'none' ? undefined : (value as NcrDisposition))}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select disposition" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Decide later</SelectItem>
                                <SelectItem value="rework">Send to rework</SelectItem>
                                <SelectItem value="return">Return to supplier</SelectItem>
                                <SelectItem value="accept_as_is">Accept as-is</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={disableSubmit || isSubmitting}>
                        {isSubmitting ? 'Submitting…' : 'Submit NCR'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function QualityMetric({ label, value, highlight }: { label: string; value: number; highlight?: boolean }) {
    return (
        <div
            className={`rounded-lg border px-4 py-3 ${highlight ? 'border-destructive/60 bg-destructive/5' : 'border-border/70 bg-muted/10'}`}
        >
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-2xl font-semibold text-foreground">{value}</p>
        </div>
    );
}

function ReceivingDetailSkeleton() {
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Receiving</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />
            <div className="space-y-4">
                <Skeleton className="h-8 w-1/3" />
                <Skeleton className="h-40 w-full" />
                <Skeleton className="h-64 w-full" />
                <Skeleton className="h-48 w-full" />
            </div>
        </div>
    );
}
