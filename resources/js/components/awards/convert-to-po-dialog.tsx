import { DocumentNumberPreview } from '@/components/documents/document-number-preview';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { useAuth } from '@/contexts/auth-context';
import type { RfqAwardCandidateLine, RfqItemAwardSummary } from '@/sdk';
import { AlertCircle } from 'lucide-react';
import { useMemo } from 'react';

interface ConvertToPoDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    awards: RfqItemAwardSummary[];
    lines: RfqAwardCandidateLine[];
    isConverting?: boolean;
    onConfirm: (awardIds: number[]) => void;
}

interface SupplierPreview {
    supplierId?: number;
    supplierName?: string;
    lineCount: number;
    currency?: string;
    totalMinor: number;
}

function formatMinorCurrency(value?: number | null, currency?: string): string {
    if (value == null || Number.isNaN(value) || !currency) {
        return '—';
    }

    const major = value / 100;

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(major);
    } catch (error) {
        void error;
        return `${major.toFixed(2)} ${currency}`;
    }
}

export function ConvertToPoDialog({
    open,
    onOpenChange,
    awards,
    lines,
    isConverting = false,
    onConfirm,
}: ConvertToPoDialogProps) {
    const { state } = useAuth();

    const candidateIndex = useMemo(() => {
        const map = new Map<
            number,
            {
                line: RfqAwardCandidateLine;
                currency?: string;
                priceMinor?: number;
            }
        >();
        lines.forEach((line) => {
            line.candidates.forEach((candidate) => {
                map.set(candidate.quoteItemId, {
                    line,
                    currency:
                        candidate.convertedCurrency ??
                        candidate.unitPriceCurrency,
                    priceMinor:
                        candidate.convertedUnitPriceMinor ??
                        candidate.unitPriceMinor ??
                        0,
                });
            });
        });
        return map;
    }, [lines]);

    const pendingAwards = awards.filter((award) => !award.poId);

    const supplierGroups = useMemo(() => {
        const groups = new Map<string, SupplierPreview>();

        pendingAwards.forEach((award) => {
            const key = String(award.supplierId ?? award.id);
            const candidateMeta = candidateIndex.get(award.quoteItemId);
            const currency = candidateMeta?.currency;
            const total =
                (candidateMeta?.priceMinor ?? 0) * (award.awardedQty ?? 1);
            const existing = groups.get(key);

            if (!existing) {
                groups.set(key, {
                    supplierId: award.supplierId ?? undefined,
                    supplierName: award.supplierName ?? undefined,
                    currency,
                    totalMinor: total,
                    lineCount: 1,
                });
                return;
            }

            existing.lineCount += 1;
            existing.totalMinor += total;
            if (!existing.currency && currency) {
                existing.currency = currency;
            }
        });

        return Array.from(groups.values());
    }, [candidateIndex, pendingAwards]);

    const disabled = !pendingAwards.length;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Convert awards to purchase orders</DialogTitle>
                    <DialogDescription>
                        We will create one purchase order per supplier using
                        your saved awards. Ship-to and bill-to defaults from{' '}
                        {state.company?.name ?? 'your company'} will be applied
                        automatically.
                    </DialogDescription>
                    <DocumentNumberPreview docType="po" className="mt-3" />
                </DialogHeader>

                {pendingAwards.length === 0 ? (
                    <div className="flex items-center gap-3 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                        <AlertCircle className="h-5 w-5" />
                        <span>
                            Create awards first or ensure they are not already
                            converted.
                        </span>
                    </div>
                ) : (
                    <div className="space-y-4">
                        <div className="rounded-md border bg-muted/40 p-3 text-sm">
                            <p className="font-semibold text-foreground">
                                {pendingAwards.length} award(s) ready
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Each supplier below will receive its own
                                purchase order draft.
                            </p>
                        </div>
                        <div className="space-y-3">
                            {supplierGroups.map((group, index) => (
                                <div
                                    key={`${group.supplierId ?? index}`}
                                    className="rounded-md border p-3"
                                >
                                    <div className="flex flex-col text-sm">
                                        <span className="font-semibold text-foreground">
                                            {group.supplierName ??
                                                `Supplier #${group.supplierId ?? '—'}`}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {group.lineCount} line
                                            {group.lineCount === 1
                                                ? ''
                                                : 's'} •{' '}
                                            {formatMinorCurrency(
                                                group.totalMinor,
                                                group.currency,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ))}
                            {supplierGroups.length > 1 ? <Separator /> : null}
                            <p className="text-xs text-muted-foreground">
                                POs inherit bill-to (
                                {state.company?.name ?? 'your company'}) and
                                will be stored as drafts for review.
                            </p>
                        </div>
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={disabled || isConverting}
                        onClick={() =>
                            onConfirm(pendingAwards.map((award) => award.id))
                        }
                    >
                        {isConverting
                            ? 'Converting…'
                            : 'Create purchase orders'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
