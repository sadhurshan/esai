import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { AlertCircle, Award, Trash2 } from 'lucide-react';
import type { RfqAwardCandidateLine, RfqItemAwardSummary } from '@/sdk';
import type { AwardLineFormValue } from '@/pages/awards/award-form-schema';
import { Fragment, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface AwardSummaryCardProps {
    lines: RfqAwardCandidateLine[];
    selections?: AwardLineFormValue[];
    awards: RfqItemAwardSummary[];
    companyCurrency?: string;
    isSaving?: boolean;
    onPersist?: () => void;
    onOpenConvert?: () => void;
    canConvert?: boolean;
    isConverting?: boolean;
    onDeleteAward?: (awardId: number) => void;
    deletingAwardId?: number | null;
}

interface SupplierSummary {
    supplierId?: number;
    supplierName?: string;
    totalMinor: number;
    currency: string;
    lineCount: number;
    minLead?: number;
    maxLead?: number;
    mixedCurrency: boolean;
}

function formatMinorCurrency(value?: number | null, currency?: string): string {
    if (value === undefined || value === null || Number.isNaN(value) || !currency) {
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

export function AwardSummaryCard({
    lines,
    selections,
    awards,
    companyCurrency,
    isSaving = false,
    onPersist,
    onOpenConvert,
    canConvert = false,
    isConverting = false,
    onDeleteAward,
    deletingAwardId,
}: AwardSummaryCardProps) {
    const summary = useMemo(() => {
        const bySupplier = new Map<string, SupplierSummary>();
        const formSelections = selections ?? [];

        lines.forEach((line, index) => {
            const selection = formSelections[index];
            if (!selection?.quoteItemId) {
                return;
            }

            const candidate = line.candidates.find((option) => option.quoteItemId === selection.quoteItemId);
            if (!candidate) {
                return;
            }

            const supplierKey = String(candidate.supplierId ?? candidate.quoteItemId);
            const currency = candidate.convertedCurrency ?? candidate.unitPriceCurrency ?? companyCurrency ?? 'USD';
            const quantity = selection.awardedQty && selection.awardedQty > 0 ? selection.awardedQty : line.quantity;
            const unitMinor = candidate.convertedUnitPriceMinor ?? candidate.unitPriceMinor ?? 0;
            const existing = bySupplier.get(supplierKey);

            if (!existing) {
                bySupplier.set(supplierKey, {
                    supplierId: candidate.supplierId ?? undefined,
                    supplierName: candidate.supplierName ?? undefined,
                    totalMinor: unitMinor * quantity,
                    currency,
                    lineCount: 1,
                    minLead: candidate.leadTimeDays ?? undefined,
                    maxLead: candidate.leadTimeDays ?? undefined,
                    mixedCurrency: false,
                });
                return;
            }

            existing.lineCount += 1;
            existing.totalMinor += unitMinor * quantity;
            if (existing.currency !== currency) {
                existing.mixedCurrency = true;
            }
            if (candidate.leadTimeDays != null) {
                existing.minLead = existing.minLead == null ? candidate.leadTimeDays : Math.min(existing.minLead, candidate.leadTimeDays);
                existing.maxLead = existing.maxLead == null ? candidate.leadTimeDays : Math.max(existing.maxLead, candidate.leadTimeDays);
            }
        });

        return Array.from(bySupplier.values());
    }, [companyCurrency, lines, selections]);

    const totalSelections = summary.reduce((acc, supplier) => acc + supplier.lineCount, 0);
    const missingSelections = lines.length - totalSelections;
    const hasMixedCurrency = summary.some((supplier) => supplier.mixedCurrency);

    return (
        <Card className="sticky top-4 border-border/70">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base font-semibold">
                    <Award className="h-4 w-4 text-primary" />
                    Award summary
                </CardTitle>
                <p className="text-sm text-muted-foreground">
                    Confirm winning quotes per RFQ line, then create awards and convert them into purchase orders per supplier.
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="rounded-md border bg-muted/40 p-3 text-sm">
                    <p className="font-semibold text-foreground">{totalSelections} of {lines.length} lines selected</p>
                    {missingSelections > 0 ? (
                        <p className="text-xs text-muted-foreground">Select winners for the remaining {missingSelections} line(s) to maximise award coverage.</p>
                    ) : (
                        <p className="text-xs text-muted-foreground">All lines have a selected supplier. You can still adjust quantities line-by-line.</p>
                    )}
                </div>

                <div className="space-y-3">
                    {summary.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Pick at least one supplier to see a per-supplier award breakdown.</p>
                    ) : (
                        summary.map((supplier, index) => (
                            <Fragment key={`${supplier.supplierId ?? supplier.supplierName ?? index}`}>
                                <div className="flex flex-col gap-1 rounded-md border p-3 text-sm">
                                    <div className="flex flex-col">
                                        <span className="font-semibold text-foreground">
                                            {supplier.supplierName ?? `Supplier #${supplier.supplierId ?? '—'}`}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {supplier.lineCount} line{supplier.lineCount === 1 ? '' : 's'} • {formatMinorCurrency(supplier.totalMinor, supplier.currency)}
                                        </span>
                                        {supplier.minLead != null ? (
                                            <span className="text-xs text-muted-foreground">
                                                Lead time {supplier.minLead}
                                                {supplier.maxLead != null && supplier.maxLead !== supplier.minLead
                                                    ? `–${supplier.maxLead}`
                                                    : ''}{' '}
                                                days
                                            </span>
                                        ) : null}
                                        {supplier.mixedCurrency ? (
                                            <span className="text-xs text-amber-600">Multiple currencies detected for this supplier.</span>
                                        ) : null}
                                    </div>
                                </div>
                                {index < summary.length - 1 ? <Separator /> : null}
                            </Fragment>
                        ))
                    )}
                </div>

                {awards.length > 0 ? (
                    <div>
                        <p className="mb-2 text-xs font-semibold uppercase text-muted-foreground">Persisted awards</p>
                        <div className="space-y-2">
                            {awards.map((award) => (
                                <div key={award.id} className="flex items-center justify-between rounded-md border p-2 text-sm">
                                    <div className="flex flex-col">
                                        <span className="font-medium text-foreground">
                                            RFQ line #{award.rfqItemId}{' '}
                                            <Badge variant="outline" className="ml-1 text-xs capitalize">
                                                {award.status}
                                            </Badge>
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            Supplier {award.supplierName ?? award.supplierId} • Qty {award.awardedQty ?? '—'}
                                        </span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground"
                                        onClick={() => onDeleteAward?.(award.id)}
                                        disabled={isSaving || deletingAwardId === award.id || Boolean(award.poId)}
                                        title={award.poId ? 'Award already converted to PO' : 'Delete award'}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="sr-only">Delete award</span>
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                {hasMixedCurrency ? (
                    <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <AlertCircle className="mt-0.5 h-4 w-4" />
                        <div>
                            <p className="font-semibold">Review currency mix</p>
                            <p className="text-xs">Some suppliers have quotes in multiple currencies. Confirm your FX rules before converting to PO.</p>
                        </div>
                    </div>
                ) : null}
            </CardContent>
            <CardFooter className="flex flex-col gap-3 border-t bg-muted/40 p-4">
                <Button type="button" onClick={onPersist} disabled={isSaving} className="w-full">
                    {isSaving ? 'Saving awards…' : 'Create awards'}
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    disabled={!canConvert || isConverting}
                    onClick={onOpenConvert}
                    className={cn('w-full', !canConvert ? 'cursor-not-allowed opacity-70' : undefined)}
                >
                    {isConverting ? 'Converting…' : 'Convert to PO'}
                </Button>
            </CardFooter>
        </Card>
    );
}
