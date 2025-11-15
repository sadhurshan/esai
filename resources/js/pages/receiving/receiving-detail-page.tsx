import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, Download, PackageOpen, PackageSearch } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { GrnStatusBadge } from '@/components/receiving/grn-status-badge';
import { FileDropzone } from '@/components/file-dropzone';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useGrn } from '@/hooks/api/receiving/use-grn';
import { useAttachGrnFile } from '@/hooks/api/receiving/use-attach-grn-file';
import type { DocumentAttachment, GrnLine } from '@/types/sourcing';
import { formatDate } from '@/lib/format';

export function ReceivingDetailPage() {
    const { grnId: grnIdParam } = useParams<{ grnId?: string }>();
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const receivingEnabled = hasFeature('inventory_enabled');

    const grnId = Number(grnIdParam);
    const grnQuery = useGrn(Number.isFinite(grnId) ? grnId : 0);
    const attachMutation = useAttachGrnFile();

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
                    ctaProps={{ onClick: () => navigate('/app/settings?tab=billing') }}
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
                            </tr>
                        </thead>
                        <tbody>
                            {grn.lines.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-6 text-center text-muted-foreground">
                                        No lines recorded on this GRN.
                                    </td>
                                </tr>
                            ) : (
                                grn.lines.map((line) => <GrnLineRow key={`${line.poLineId}-${line.id ?? line.poLineId}`} line={line} />)
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

function GrnLineRow({ line }: { line: GrnLine }) {
    const varianceVariant = line.variance === 'over' ? 'destructive' : line.variance === 'short' ? 'secondary' : 'outline';

    return (
        <tr className="border-t border-border/60">
            <td className="py-3 pr-4">
                <p className="font-medium text-foreground">{line.description ?? `PO line #${line.poLineId}`}</p>
                <p className="text-xs text-muted-foreground">Line {line.lineNo ?? line.poLineId}</p>
            </td>
            <td className="py-3 pr-4">{formatQuantity(line.orderedQty, line.uom)}</td>
            <td className="py-3 pr-4">{formatQuantity(line.previouslyReceived, line.uom)}</td>
            <td className="py-3 pr-4 font-semibold text-foreground">{formatQuantity(line.qtyReceived, line.uom)}</td>
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
        </tr>
    );
}

function formatQuantity(value?: number | null, uom?: string | null): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }
    const formatted = Math.abs(value) >= 1 ? value.toLocaleString(undefined, { maximumFractionDigits: 2 }) : value.toFixed(3);
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
