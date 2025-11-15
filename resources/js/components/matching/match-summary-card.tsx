import { useEffect, useMemo, useState } from 'react';

import { AlertTriangle, CheckCircle2, Clock } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import type { MatchCandidate, MatchDiscrepancy, MatchResolutionInput } from '@/types/sourcing';

import { formatCurrencyMinor } from '@/lib/money';

import { DiscrepancyBadge } from './discrepancy-badge';

const STATUS_OPTIONS: MatchResolutionInput['decisions'][number]['status'][] = ['accept', 'reject', 'credit', 'pending'];

type DecisionState = Record<string, { status: MatchResolutionInput['decisions'][number]['status']; notes?: string }>;

interface MatchSummaryCardProps {
    candidate?: MatchCandidate | null;
    isSubmitting?: boolean;
    onSubmit?: (payload: MatchResolutionInput) => void;
}

const getInitialDecisions = (candidate?: MatchCandidate | null): DecisionState => {
    if (!candidate) {
        return {};
    }

    const next: DecisionState = {};
    candidate.lines.forEach((line) => {
        line.discrepancies.forEach((disc) => {
            next[`${line.id}-${disc.id}`] = {
                status: disc.severity === 'info' ? 'accept' : 'pending',
            };
        });
    });

    return next;
};

const ensureInvoiceId = (candidate?: MatchCandidate | null) => candidate?.invoices?.[0]?.id ?? '';

export const MatchSummaryCard = ({ candidate, isSubmitting, onSubmit }: MatchSummaryCardProps) => {
    const [decisions, setDecisions] = useState<DecisionState>(() => getInitialDecisions(candidate));

    useEffect(() => {
        setDecisions(getInitialDecisions(candidate));
    }, [candidate]);

    const isReady = Boolean(candidate && ensureInvoiceId(candidate));

    const varianceStats = useMemo(() => {
        if (!candidate) {
            return null;
        }

        return {
            po: formatCurrencyMinor(candidate.poTotalMinor, candidate.currency),
            received: formatCurrencyMinor(candidate.receivedTotalMinor, candidate.currency),
            invoiced: formatCurrencyMinor(candidate.invoicedTotalMinor, candidate.currency),
            variance: formatCurrencyMinor(candidate.varianceMinor, candidate.currency),
        };
    }, [candidate]);

    const handleDecisionChange = (
        lineId: string,
        discrepancy: MatchDiscrepancy,
        status: MatchResolutionInput['decisions'][number]['status'],
    ) => {
        setDecisions((prev) => ({
            ...prev,
            [`${lineId}-${discrepancy.id}`]: {
                ...(prev[`${lineId}-${discrepancy.id}`] ?? { status: 'pending' }),
                status,
            },
        }));
    };

    const handleNotesChange = (lineId: string, discrepancy: MatchDiscrepancy, notes: string) => {
        setDecisions((prev) => ({
            ...prev,
            [`${lineId}-${discrepancy.id}`]: {
                ...(prev[`${lineId}-${discrepancy.id}`] ?? { status: 'pending' }),
                notes: notes.trim().length ? notes : undefined,
            },
        }));
    };

    const pendingDecisions = useMemo(() => {
        if (!candidate) {
            return false;
        }

        return candidate.lines.some((line) =>
            line.discrepancies.some((disc) => (decisions[`${line.id}-${disc.id}`]?.status ?? 'pending') === 'pending'),
        );
    }, [candidate, decisions]);

    const handleSubmit = () => {
        if (!candidate || !onSubmit) {
            return;
        }

        const invoiceId = ensureInvoiceId(candidate);
        if (!invoiceId) {
            return;
        }

        const payload: MatchResolutionInput = {
            invoiceId,
            purchaseOrderId: candidate.purchaseOrderId,
            grnIds: candidate.grns?.map((grn) => grn.id).filter((id): id is number => typeof id === 'number') ?? [],
            decisions: candidate.lines.flatMap((line) =>
                line.discrepancies.map((disc) => ({
                    lineId: line.id,
                    type: disc.type,
                    status: decisions[`${line.id}-${disc.id}`]?.status ?? 'pending',
                    notes: decisions[`${line.id}-${disc.id}`]?.notes,
                })),
            ),
        };

        onSubmit(payload);
    };

    if (!candidate) {
        return (
            <Card className="h-full min-h-[320px]">
                <CardHeader>
                    <CardTitle className="text-base">Match summary</CardTitle>
                </CardHeader>
                <CardContent className="flex h-[260px] items-center justify-center text-sm text-muted-foreground">
                    Select a record to review discrepancies.
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="h-full">
            <CardHeader className="space-y-2">
                <CardTitle className="text-base">{candidate.purchaseOrderNumber ?? `PO #${candidate.purchaseOrderId}`}</CardTitle>
                <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
                    <span className="inline-flex items-center gap-1">
                        <Clock className="h-4 w-4" />
                        Last activity {candidate.lastActivityAt ? new Date(candidate.lastActivityAt).toLocaleString() : '—'}
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <AlertTriangle className="h-4 w-4 text-amber-500" />
                        {candidate.lines.reduce((acc, line) => acc + line.discrepancies.length, 0)} variances
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                        {candidate.grns?.length ?? 0} GRNs ∙ {candidate.invoices?.length ?? 0} invoices
                    </span>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                {varianceStats && (
                    <div className="grid grid-cols-2 gap-4 rounded-lg border border-border p-3 text-sm">
                        <div>
                            <p className="text-muted-foreground">PO total</p>
                            <p className="font-medium">{varianceStats.po}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Received total</p>
                            <p className="font-medium">{varianceStats.received}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Invoiced</p>
                            <p className="font-medium">{varianceStats.invoiced}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Variance</p>
                            <p className="font-medium">{varianceStats.variance}</p>
                        </div>
                    </div>
                )}

                <Separator />

                <div className="space-y-4">
                    {candidate.lines.map((line) => (
                        <div key={line.id} className="rounded-lg border border-border p-4">
                            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p className="text-sm font-medium">Line {line.lineNo ?? line.poLineId}</p>
                                    <p className="text-sm text-muted-foreground">{line.itemDescription ?? 'Line item'}</p>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Ordered {line.orderedQty} {line.uom ?? ''} ∙ Received {line.receivedQty ?? 0} ∙ Invoiced {line.invoicedQty ?? 0}
                                </div>
                            </div>
                            <div className="mt-4 space-y-4">
                                {line.discrepancies.map((disc) => (
                                    <div key={disc.id} className="space-y-2 rounded-md border border-dashed border-border p-3">
                                        <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                            <DiscrepancyBadge discrepancy={disc} />
                                            <div className="text-xs text-muted-foreground">
                                                {(disc.difference ?? disc.amountMinor) && (
                                                    <span>
                                                        Δ {disc.difference ?? ''}
                                                        {disc.amountMinor ? ` / ${formatCurrencyMinor(disc.amountMinor, disc.currency)}` : ''}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="grid gap-3 md:grid-cols-[200px_minmax(0,1fr)]">
                                            <div className="space-y-1">
                                                <Label className="text-xs text-muted-foreground">Resolution</Label>
                                                <Select
                                                    value={decisions[`${line.id}-${disc.id}`]?.status ?? 'pending'}
                                                    onValueChange={(value) =>
                                                        handleDecisionChange(
                                                            line.id,
                                                            disc,
                                                            value as MatchResolutionInput['decisions'][number]['status'],
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="h-9 text-sm">
                                                        <SelectValue placeholder="Choose" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {STATUS_OPTIONS.map((option) => (
                                                            <SelectItem key={option} value={option} className="text-sm capitalize">
                                                                {option}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-xs text-muted-foreground">Notes</Label>
                                                <Textarea
                                                    rows={2}
                                                    placeholder="Add context for finance reviewers"
                                                    className="text-sm"
                                                    value={decisions[`${line.id}-${disc.id}`]?.notes ?? ''}
                                                    onChange={(event) =>
                                                        handleNotesChange(line.id, disc, event.currentTarget.value)
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                    {!candidate.lines.length && <Skeleton className="h-24 w-full" />}
                </div>
            </CardContent>
            <CardFooter className="flex flex-col gap-2 border-t border-border pt-4 md:flex-row md:items-center md:justify-between">
                <p className="text-sm text-muted-foreground">
                    Decisions sync back to PO change orders and invoice notes automatically.
                </p>
                <Button onClick={handleSubmit} disabled={!isReady || pendingDecisions || isSubmitting} aria-busy={isSubmitting}>
                    {pendingDecisions ? 'Resolve all discrepancies first' : 'Submit resolutions'}
                </Button>
            </CardFooter>
        </Card>
    );
};
