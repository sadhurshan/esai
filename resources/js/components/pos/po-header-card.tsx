import { RefreshCcw, Send, Ban, Download, FilePlus } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { MoneyCell } from '@/components/quotes/money-cell';
import { PoStatusBadge } from '@/components/pos/po-status-badge';
import { AckStatusChip } from '@/components/pos/ack-status-chip';
import type { PurchaseOrderDetail, PurchaseOrderSummary } from '@/types/sourcing';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

interface PoHeaderCardProps {
    po: PurchaseOrderSummary | PurchaseOrderDetail;
    className?: string;
    onRecalculate?: () => void;
    onSend?: () => void;
    onCancel?: () => void;
    onExport?: () => void;
    onCreateInvoice?: () => void;
    isRecalculating?: boolean;
    isCancelling?: boolean;
    isSending?: boolean;
    isExporting?: boolean;
    isCreatingInvoice?: boolean;
    canCreateInvoice?: boolean;
}

export function PoHeaderCard({
    po,
    className,
    onRecalculate,
    onSend,
    onCancel,
    onExport,
    onCreateInvoice,
    isRecalculating = false,
    isCancelling = false,
    isSending = false,
    isExporting = false,
    isCreatingInvoice = false,
    canCreateInvoice = true,
}: PoHeaderCardProps) {
    return (
        <Card className={cn('border-border/70', className)}>
            <CardHeader>
                <div className="flex flex-wrap items-center gap-3">
                    <CardTitle className="text-2xl font-semibold text-foreground">PO #{po.poNumber}</CardTitle>
                    <PoStatusBadge status={po.status} />
                    <AckStatusChip
                        status={po.ackStatus}
                        sentAt={po.sentAt}
                        acknowledgedAt={po.acknowledgedAt}
                        ackReason={po.ackReason}
                        latestDelivery={po.latestDelivery}
                    />
                    {po.revisionNo ? (
                        <span className="text-xs uppercase tracking-wide text-muted-foreground">Rev {po.revisionNo}</span>
                    ) : null}
                </div>
                <CardDescription>
                    <span className="font-medium text-foreground">{po.supplierName ?? 'Unassigned supplier'}</span>
                    {po.createdAt ? <span className="text-muted-foreground"> • Issued {formatDate(po.createdAt)}</span> : null}
                    {po.incoterm ? <span className="text-muted-foreground"> • Incoterm {po.incoterm}</span> : null}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid gap-4 md:grid-cols-3">
                    <MoneyCell amountMinor={po.subtotalMinor ?? po.totalMinor} currency={po.currency} label="Subtotal" />
                    <MoneyCell amountMinor={po.taxAmountMinor} currency={po.currency} label="Tax" />
                    <MoneyCell amountMinor={po.totalMinor} currency={po.currency} label="Total" />
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    {onRecalculate ? (
                        <Button type="button" size="sm" onClick={onRecalculate} disabled={isRecalculating}>
                            <RefreshCcw className="mr-2 h-4 w-4" />
                            Recalculate totals
                        </Button>
                    ) : null}
                    {onCreateInvoice ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={onCreateInvoice}
                            disabled={!canCreateInvoice || isCreatingInvoice}
                        >
                            <FilePlus className="mr-2 h-4 w-4" />
                            Create invoice
                        </Button>
                    ) : null}
                    {onSend ? (
                        <Button type="button" size="sm" variant="secondary" onClick={onSend} disabled={isSending}>
                            <Send className="mr-2 h-4 w-4" />
                            Send to supplier
                        </Button>
                    ) : null}
                    {onExport ? (
                        <Button type="button" size="sm" variant="outline" onClick={onExport} disabled={isExporting}>
                            <Download className="mr-2 h-4 w-4" />
                            Export PDF
                        </Button>
                    ) : null}
                    {onCancel ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="text-destructive hover:text-destructive"
                            onClick={onCancel}
                            disabled={isCancelling}
                        >
                            <Ban className="mr-2 h-4 w-4" />
                            Cancel PO
                        </Button>
                    ) : null}
                    {!onRecalculate && !onSend && !onExport && !onCancel && !onCreateInvoice ? (
                        <span className="text-sm text-muted-foreground">
                            Actions unavailable for your role.
                        </span>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}
