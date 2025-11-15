import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactElement } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm, useWatch } from 'react-hook-form';
import { FileText } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/empty-state';
import { Label } from '@/components/ui/label';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { QuoteLineEditor } from '@/components/quotes/quote-line-editor';
import { WithdrawConfirmDialog } from '@/components/quotes/withdraw-confirm-dialog';
import { MoneyCell } from '@/components/quotes/money-cell';
import { QuoteStatusBadge } from '@/components/quotes/quote-status-badge';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useQuote } from '@/hooks/api/quotes/use-quote';
import { useSubmitQuote } from '@/hooks/api/quotes/use-submit-quote';
import { useReviseQuote } from '@/hooks/api/quotes/use-revise-quote';
import { useWithdrawQuote } from '@/hooks/api/quotes/use-withdraw-quote';
import { useQuoteLines } from '@/hooks/api/quotes/use-quote-lines';
import { useRfqLines } from '@/hooks/api/rfqs/use-rfq-lines';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import type { SupplierQuoteFormValues } from '@/pages/quotes/supplier-quote-schema';
import { supplierQuoteFormSchema } from '@/pages/quotes/supplier-quote-schema';
import type { Quote, QuoteItem, QuoteStatusEnum, SubmitQuoteRevisionRequest, RfqItem } from '@/sdk';
import { HttpError, type QuoteLineUpdateRequest } from '@/sdk';

const READ_ONLY_STATUSES: QuoteStatusEnum[] = ['withdrawn', 'expired', 'awarded', 'lost'];
const MIN_MINOR_UNIT = 2;

export function SupplierQuoteEditPage() {
    const { quoteId } = useParams<{ quoteId: string }>();
    const navigate = useNavigate();
    const { hasFeature, state, notifyPlanLimit } = useAuth();
    const supplierRole = state.user?.role === 'supplier';
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const quotesEnabled = hasFeature('quotes_enabled');
    const supplierPortalEnabled = hasFeature('supplier_portal_enabled') || supplierRole;
    const canAccess = !featureFlagsLoaded || (quotesEnabled && supplierPortalEnabled);

    const quoteQuery = useQuote(quoteId, { enabled: Boolean(quoteId) && canAccess });
    const quote = quoteQuery.data?.quote;
    const rfqLinesQuery = useRfqLines({ rfqId: quote?.rfqId ?? null });
    const moneySettingsQuery = useMoneySettings();
    const { updateLine } = useQuoteLines();
    const submitQuote = useSubmitQuote();
    const reviseQuote = useReviseQuote();
    const withdrawQuote = useWithdrawQuote();

    const rfqLines = useMemo(() => rfqLinesQuery.items ?? [], [rfqLinesQuery.items]);
    const quantityMap = useMemo(() => mapLineQuantity(rfqLines, quote?.items ?? []), [quote?.items, rfqLines]);
    const minorUnit = inferMinorUnit(moneySettingsQuery.data) ?? MIN_MINOR_UNIT;

    const form = useForm<SupplierQuoteFormValues>({
        resolver: zodResolver(supplierQuoteFormSchema),
        defaultValues: quote ? buildFormValuesFromQuote(quote, rfqLines, minorUnit) : undefined,
    });
    const watchedLines = useWatch({ control: form.control, name: 'lines' });
    const watchedCurrency = useWatch({ control: form.control, name: 'currency' });
    const [withdrawDialogOpen, setWithdrawDialogOpen] = useState(false);
    const resetSignatureRef = useRef<string | null>(null);

    const resetSignature = useMemo(() => {
        if (!quote) {
            return null;
        }
        return `${quote.id}-${rfqLines.length}-${minorUnit}`;
    }, [minorUnit, quote, rfqLines.length]);

    useEffect(() => {
        if (!quote || !resetSignature) {
            return;
        }
        if (resetSignatureRef.current === resetSignature) {
            return;
        }
        form.reset(buildFormValuesFromQuote(quote, rfqLines, minorUnit));
        resetSignatureRef.current = resetSignature;
    }, [form, minorUnit, quote, resetSignature, rfqLines]);

    const reviewTotals = useMemo(() => {
        return calculateReviewTotals(watchedLines, quantityMap, minorUnit);
    }, [minorUnit, quantityMap, watchedLines]);

    if (featureFlagsLoaded && !supplierPortalEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier quotes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Supplier workspace not enabled"
                    description="Ask the buyer to enable supplier portal access to manage quotes."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                />
            </div>
        );
    }

    if (!quoteId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier quotes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Select a quote"
                    description="Open this view from a draft or submitted quote."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="Back to RFQs"
                    ctaProps={{ onClick: () => navigate('/app/rfqs') }}
                />
            </div>
        );
    }

    if (quoteQuery.isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quote</title>
                </Helmet>
                <PlanUpgradeBanner />
                <Card className="border-sidebar-border/60">
                    <CardContent className="p-8 text-sm text-muted-foreground">Loading quote…</CardContent>
                </Card>
            </div>
        );
    }

    if (quoteQuery.isError || !quote) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quote</title>
                </Helmet>
                <PlanUpgradeBanner />
                <Alert variant="destructive">
                    <AlertTitle>Quote unavailable</AlertTitle>
                    <AlertDescription>We could not find that quote or you lack access.</AlertDescription>
                </Alert>
            </div>
        );
    }

    const isReadOnly = READ_ONLY_STATUSES.includes(quote.status as QuoteStatusEnum);
    const rfqNumber = String(quote.rfqId);

    const handlePersistLines = async (values: SupplierQuoteFormValues) => {
        if (!quote.items?.length) {
            return;
        }

        for (const line of values.lines) {
            const existing = quote.items.find((item) => String(item.rfqItemId) === line.rfqItemId);
            if (!existing) {
                continue;
            }

            const payload = buildLineUpdatePayload(existing, line, minorUnit);
            if (!payload) {
                continue;
            }

            await updateLine.mutateAsync({
                quoteId: quote.id,
                quoteItemId: existing.id,
                payload,
                rfqId: quote.rfqId,
            });
        }
    };

    const handleSubmitDraft = async (values: SupplierQuoteFormValues) => {
        try {
            await handlePersistLines(values);
            publishToast({ variant: 'success', title: 'Draft saved', description: 'All line edits synced.' });
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const handleSubmitDraftAndSend = async (values: SupplierQuoteFormValues) => {
        try {
            await handlePersistLines(values);
            await submitQuote.mutateAsync({ quoteId: quote.id, rfqId: quote.rfqId });
            publishToast({ variant: 'success', title: 'Quote submitted', description: 'Buyer notified of your submission.' });
            navigate(`/app/quotes/${quote.id}`);
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const handleSubmitRevision = async (values: SupplierQuoteFormValues) => {
        const revisionNote = values.revisionNote?.trim();
        if (!revisionNote) {
            publishToast({ variant: 'destructive', title: 'Revision note required', description: 'Explain what changed.' });
            return;
        }

        try {
            const payload: SubmitQuoteRevisionRequest = {
                note: revisionNote,
                items: values.lines.map((line) => ({
                    rfqItemId: line.rfqItemId,
                    quantity: quantityMap[line.rfqItemId] ?? 1,
                    unitPriceMinor: toMinorUnits(Number(line.unitPrice), minorUnit),
                })),
            };
            await reviseQuote.mutateAsync({ quoteId: quote.id, rfqId: quote.rfqId, payload });
            publishToast({ variant: 'success', title: 'Revision submitted', description: 'New revision is live for the buyer.' });
            form.setValue('revisionNote', '');
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const handleWithdraw = async (reason: string) => {
        try {
            await withdrawQuote.mutateAsync({ quoteId: quote.id, rfqId: quote.rfqId, reason });
            publishToast({ variant: 'success', title: 'Quote withdrawn', description: 'The buyer saw your reason immediately.' });
            setWithdrawDialogOpen(false);
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const actionButtons = buildActionButtons(quote.status as QuoteStatusEnum, {
        onSaveDraft: form.handleSubmit(handleSubmitDraft),
        onSubmit: form.handleSubmit(handleSubmitDraftAndSend),
        onRevise: form.handleSubmit(handleSubmitRevision),
        onWithdraw: () => setWithdrawDialogOpen(true),
        processing: updateLine.isPending || submitQuote.isPending || reviseQuote.isPending || withdrawQuote.isPending,
    });

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Edit quote · RFQ {rfqNumber}</title>
            </Helmet>

            <PlanUpgradeBanner />

            <div className="flex flex-col gap-6 lg:flex-row">
                <div className="flex-1 space-y-6">
                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>Quote summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Status</p>
                                <QuoteStatusBadge status={quote.status} />
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Total</p>
                                <MoneyCell amountMinor={quote.totalMinor} currency={quote.currency} />
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Revision</p>
                                <p className="text-sm font-medium">Rev {quote.revisionNo ?? 1}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>Line pricing</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <QuoteLineEditor
                                form={form}
                                rfqLines={rfqLines}
                                currency={watchedCurrency ?? quote.currency}
                                disabled={isReadOnly}
                            />
                        </CardContent>
                    </Card>

                    {quote.status === 'submitted' ? (
                        <Card className="border-sidebar-border/60">
                            <CardHeader>
                                <CardTitle>Revision note</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Label htmlFor="revision-note">Explain what changed</Label>
                                <Textarea
                                    id="revision-note"
                                    rows={3}
                                    placeholder="Example: Updated unit prices based on clarified tolerances."
                                    disabled={reviseQuote.isPending}
                                    {...form.register('revisionNote')}
                                />
                            </CardContent>
                        </Card>
                    ) : null}
                </div>

                <aside className="w-full max-w-md space-y-4">
                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>Review</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">RFQ</p>
                                <p className="text-base font-semibold">{rfqNumber}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Working total</p>
                                <MoneyCell amountMinor={reviewTotals.totalMinor} currency={watchedCurrency ?? quote.currency} />
                            </div>
                            <div>
                                <p className="text-xs uppercase text-muted-foreground">Line progress</p>
                                <p className="text-sm">
                                    {reviewTotals.completedLines}/{watchedLines?.length ?? 0} complete
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>Attachments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {quote.attachments && quote.attachments.length > 0 ? (
                                <ul className="space-y-2">
                                    {quote.attachments.map((attachment) => (
                                        <li key={attachment.id ?? attachment.filename} className="rounded-md border px-3 py-2 text-sm">
                                            <p className="font-medium">{attachment.filename ?? 'Attachment'}</p>
                                            <p className="text-xs text-muted-foreground">{attachment.mime ?? 'File'}</p>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">No attachments uploaded.</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">{actionButtons}</CardContent>
                    </Card>
                </aside>
            </div>

            <WithdrawConfirmDialog
                open={withdrawDialogOpen}
                onOpenChange={setWithdrawDialogOpen}
                onConfirm={handleWithdraw}
                isProcessing={withdrawQuote.isPending}
            />
        </div>
    );
}

function buildFormValuesFromQuote(quote: Quote, rfqLines: RfqItem[], minorUnit: number): SupplierQuoteFormValues {
    const rfqQuantities = mapLineQuantity(rfqLines, quote.items ?? []);
    return {
        currency: quote.currency,
        note: quote.note ?? '',
        leadTimeDays: quote.leadTimeDays != null ? String(quote.leadTimeDays) : '',
        revisionNote: '',
        attachments: quote.attachments?.map((attachment) => attachment.id ?? attachment.path ?? '') ?? [],
        lines:
            quote.items?.map((item) => ({
                rfqItemId: String(item.rfqItemId),
                quantity: item.quantity ?? rfqQuantities[String(item.rfqItemId)] ?? 1,
                unitPrice: formatMinorToMajor(item.unitPriceMinor, minorUnit),
                leadTimeDays: item.leadTimeDays != null ? String(item.leadTimeDays) : '',
                note: item.note ?? '',
            })) ?? [],
    };
}

function mapLineQuantity(lines: RfqItem[], fallbackItems: QuoteItem[]): Record<string, number> {
    const map = new Map<string, number>();
    lines.forEach((line) => map.set(String(line.id), line.quantity ?? 1));
    fallbackItems.forEach((item) => {
        const key = String(item.rfqItemId);
        if (!map.has(key)) {
            map.set(key, item.quantity ?? 1);
        }
    });
    return Object.fromEntries(map.entries());
}

function inferMinorUnit(settings?: { pricingCurrency?: { minorUnit?: number }; baseCurrency?: { minorUnit?: number } }) {
    return settings?.pricingCurrency?.minorUnit ?? settings?.baseCurrency?.minorUnit ?? null;
}

function calculateReviewTotals(
    lines: SupplierQuoteFormValues['lines'],
    quantityMap: Record<string, number>,
    minorUnit: number,
) {
    if (!lines || lines.length === 0) {
        return { totalMinor: 0, completedLines: 0 };
    }

    const totalMinor = lines.reduce((acc, line) => {
        if (!line.unitPrice || !line.leadTimeDays) {
            return acc;
        }
        const qty = quantityMap[line.rfqItemId] ?? 1;
        const unitPrice = Number(line.unitPrice);
        if (Number.isNaN(unitPrice)) {
            return acc;
        }
        return acc + toMinorUnits(unitPrice, minorUnit) * qty;
    }, 0);

    const completedLines = lines.reduce((acc, line) => {
        if (line.unitPrice && line.leadTimeDays) {
            return acc + 1;
        }
        return acc;
    }, 0);

    return { totalMinor, completedLines };
}

function toMinorUnits(amount: number, minorUnit: number) {
    const factor = 10 ** (minorUnit ?? MIN_MINOR_UNIT);
    return Math.round(amount * factor);
}

function formatMinorToMajor(value: number | undefined, minorUnit: number): string {
    if (!value && value !== 0) {
        return '';
    }
    const factor = 10 ** (minorUnit ?? MIN_MINOR_UNIT);
    return (value / factor).toString();
}

function buildLineUpdatePayload(
    existing: QuoteItem,
    line: SupplierQuoteFormValues['lines'][number],
    minorUnit: number,
): QuoteLineUpdateRequest | null {
    const updates: QuoteLineUpdateRequest = {};
    const desiredPrice = Number(line.unitPrice);
    if (!Number.isNaN(desiredPrice)) {
        const nextMinor = toMinorUnits(desiredPrice, minorUnit);
        if (nextMinor !== existing.unitPriceMinor) {
            updates.unitPriceMinor = nextMinor;
        }
    }

    const nextLead = Number(line.leadTimeDays);
    if (!Number.isNaN(nextLead) && nextLead !== (existing.leadTimeDays ?? 0)) {
        updates.leadTimeDays = nextLead;
    }

    const desiredNote = line.note?.trim() ?? '';
    if ((desiredNote || undefined) !== (existing.note ?? undefined)) {
        updates.note = desiredNote.length ? desiredNote : undefined;
    }

    if (Object.keys(updates).length === 0) {
        return null;
    }

    return updates;
}

function handleQuoteError(error: unknown, notifyPlanLimit: (notice: { code?: string | null; message?: string | null }) => void) {
    if (error instanceof HttpError) {
        if (error.response.status === 402) {
            notifyPlanLimit({ code: 'quotes', message: 'Upgrade required for quote workflows.' });
            publishToast({ variant: 'destructive', title: 'Plan upgrade required', description: 'Your workspace plan blocks this action.' });
            return;
        }
        if (error.response.status === 403) {
            publishToast({ variant: 'destructive', title: 'Forbidden', description: 'You lack permission to modify this quote.' });
            return;
        }
    }

    const message = error instanceof Error ? error.message : 'Request failed. Please retry.';
    publishToast({ variant: 'destructive', title: 'Request failed', description: message });
}

function buildActionButtons(
    status: QuoteStatusEnum,
    handlers: {
        onSaveDraft: () => void;
        onSubmit: () => void;
        onRevise: () => void;
        onWithdraw: () => void;
        processing: boolean;
    },
) {
    const buttons: ReactElement[] = [];

    if (status === 'draft') {
        buttons.push(
            <Button key="save" variant="outline" className="w-full" onClick={handlers.onSaveDraft} disabled={handlers.processing}>
                Save draft
            </Button>,
        );
        buttons.push(
            <Button key="submit" className="w-full" onClick={handlers.onSubmit} disabled={handlers.processing}>
                Submit quote
            </Button>,
        );
    } else if (status === 'submitted') {
        buttons.push(
            <Button key="revise" className="w-full" onClick={handlers.onRevise} disabled={handlers.processing}>
                Submit revision
            </Button>,
        );
        buttons.push(
            <Button key="withdraw" variant="destructive" className="w-full" onClick={handlers.onWithdraw} disabled={handlers.processing}>
                Withdraw quote
            </Button>,
        );
    } else {
        buttons.push(
            <Button key="read-only" className="w-full" disabled>
                No further actions
            </Button>,
        );
    }

    return buttons;
}
