import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { MoneyCell } from '@/components/quotes/money-cell';
import { QuoteAttachmentsInput } from '@/components/quotes/quote-attachments-input';
import { QuoteLineEditor } from '@/components/quotes/quote-line-editor';
import { QuoteStatusBadge } from '@/components/quotes/quote-status-badge';
import { WithdrawConfirmDialog } from '@/components/quotes/withdraw-confirm-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useQuote } from '@/hooks/api/quotes/use-quote';
import { useQuoteLines } from '@/hooks/api/quotes/use-quote-lines';
import { useReviseQuote } from '@/hooks/api/quotes/use-revise-quote';
import { useSubmitQuote } from '@/hooks/api/quotes/use-submit-quote';
import { useUpdateQuoteDraft } from '@/hooks/api/quotes/use-update-quote-draft';
import { useWithdrawQuote } from '@/hooks/api/quotes/use-withdraw-quote';
import { useRfq } from '@/hooks/api/rfqs/use-rfq';
import { useRfqLines } from '@/hooks/api/rfqs/use-rfq-lines';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import {
    getRfqDeadlineDate,
    isResponseWindowClosed,
} from '@/lib/rfq-response-window';
import {
    haveSameTaxCodeIds,
    normalizeTaxCodeIds,
} from '@/lib/tax-code-helpers';
import type { SupplierQuoteFormValues } from '@/pages/quotes/supplier-quote-schema';
import { supplierQuoteFormSchema } from '@/pages/quotes/supplier-quote-schema';
import type {
    Quote,
    QuoteItem,
    QuoteStatusEnum,
    RfqItem,
    SubmitQuoteRevisionRequest,
} from '@/sdk';
import { HttpError, type QuoteLineUpdateRequest } from '@/sdk';
import type { DocumentAttachment } from '@/types/sourcing';
import { zodResolver } from '@hookform/resolvers/zod';
import { FileText } from 'lucide-react';
import type { ReactElement, ReactNode } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useForm, useWatch } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';

const READ_ONLY_STATUSES: QuoteStatusEnum[] = [
    'withdrawn',
    'expired',
    'awarded',
    'lost',
];
const MIN_MINOR_UNIT = 2;

export function SupplierQuoteEditPage() {
    const { quoteId } = useParams<{ quoteId: string }>();
    const navigate = useNavigate();
    const { hasFeature, state, notifyPlanLimit, activePersona } = useAuth();
    const { formatDate } = useFormatting();
    const supplierRole = state.user?.role === 'supplier';
    const isSupplierPersona = activePersona?.type === 'supplier';
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const quotesEnabled = hasFeature('quotes_enabled') || isSupplierPersona;
    const supplierPortalEnabled =
        hasFeature('supplier_portal_enabled') ||
        supplierRole ||
        isSupplierPersona;
    const canAccess =
        !featureFlagsLoaded || (quotesEnabled && supplierPortalEnabled);

    const quoteQuery = useQuote(quoteId, {
        enabled: Boolean(quoteId) && canAccess,
    });
    const quote = quoteQuery.data?.quote;
    const rfqDetailQuery = useRfq(quote?.rfqId ?? null, {
        enabled: Boolean(quote?.rfqId) && canAccess,
    });
    const rfqLinesQuery = useRfqLines({ rfqId: quote?.rfqId ?? null });
    const moneySettingsQuery = useMoneySettings();
    const { updateLine } = useQuoteLines();
    const submitQuote = useSubmitQuote();
    const reviseQuote = useReviseQuote();
    const withdrawQuote = useWithdrawQuote();
    const updateQuoteDraft = useUpdateQuoteDraft();

    const rfqLines = useMemo(
        () => rfqLinesQuery.items ?? [],
        [rfqLinesQuery.items],
    );
    const quantityMap = useMemo(
        () => mapLineQuantity(rfqLines, quote?.items ?? []),
        [quote?.items, rfqLines],
    );
    const minorUnit = inferMinorUnit(moneySettingsQuery.data) ?? MIN_MINOR_UNIT;
    const currencyOptions = useMemo(
        () => inferCurrencyOptions(moneySettingsQuery.data, quote?.currency),
        [moneySettingsQuery.data, quote?.currency],
    );

    const form = useForm<SupplierQuoteFormValues>({
        resolver: zodResolver(supplierQuoteFormSchema),
        defaultValues: quote
            ? buildFormValuesFromQuote(quote, rfqLines, minorUnit)
            : undefined,
    });
    const watchedLines = useWatch({ control: form.control, name: 'lines' });
    const watchedCurrency = useWatch({
        control: form.control,
        name: 'currency',
    });
    const watchedMinOrderQty = useWatch({
        control: form.control,
        name: 'minOrderQty',
    });
    const [withdrawDialogOpen, setWithdrawDialogOpen] = useState(false);
    const resetSignatureRef = useRef<string | null>(null);

    const resetSignature = useMemo(() => {
        if (!quote) {
            return null;
        }

        const summaryFingerprint = JSON.stringify({
            currency: quote.currency ?? '',
            minOrderQty: quote.minOrderQty ?? '',
            leadTimeDays: quote.leadTimeDays ?? '',
            incoterm: quote.incoterm ?? '',
            paymentTerms: quote.paymentTerms ?? '',
            note: quote.note ?? '',
        });

        return `${quote.id}-${rfqLines.length}-${minorUnit}-${summaryFingerprint}`;
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

    const rfqDetail = rfqDetailQuery.data ?? null;
    const rfqDeadlineDate = getRfqDeadlineDate(rfqDetail);
    const responseWindowClosed = isResponseWindowClosed(rfqDetail);
    const deadlineLabel = rfqDeadlineDate ? formatDate(rfqDeadlineDate) : null;

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
                    icon={
                        <FileText className="h-10 w-10 text-muted-foreground" />
                    }
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
                    icon={
                        <FileText className="h-10 w-10 text-muted-foreground" />
                    }
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
                    <CardContent className="p-8 text-sm text-muted-foreground">
                        Loading quote…
                    </CardContent>
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
                    <AlertDescription>
                        We could not find that quote or you lack access.
                    </AlertDescription>
                </Alert>
            </div>
        );
    }

    const statusLocked = READ_ONLY_STATUSES.includes(
        quote.status as QuoteStatusEnum,
    );
    const isReadOnly = statusLocked || responseWindowClosed;
    const rfqNumber = String(quote.rfqId);

    const handlePersistLines = async (values: SupplierQuoteFormValues) => {
        if (quote.status === 'draft') {
            const payload = buildDraftUpdatePayload(values);
            await updateQuoteDraft.mutateAsync({
                rfqId: quote.rfqId,
                quoteId: quote.id,
                payload,
            });
        }

        if (!quote.items?.length) {
            return;
        }

        for (const line of values.lines) {
            const existing = quote.items.find(
                (item) => String(item.rfqItemId) === line.rfqItemId,
            );
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
            publishToast({
                variant: 'success',
                title: 'Draft saved',
                description: 'All line edits synced.',
            });
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const handleSubmitDraftAndSend = async (
        values: SupplierQuoteFormValues,
    ) => {
        try {
            await handlePersistLines(values);
            await submitQuote.mutateAsync({
                quoteId: quote.id,
                rfqId: quote.rfqId,
            });
            publishToast({
                variant: 'success',
                title: 'Quote submitted',
                description: 'Buyer notified of your submission.',
            });
            navigate(`/app/supplier/quotes/${quote.id}`);
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const handleSubmitRevision = async (values: SupplierQuoteFormValues) => {
        const revisionNote = values.revisionNote?.trim();
        if (!revisionNote) {
            publishToast({
                variant: 'destructive',
                title: 'Revision note required',
                description: 'Explain what changed.',
            });
            return;
        }

        try {
            const payload: SubmitQuoteRevisionRequest = {
                note: revisionNote,
                items: values.lines.map((line) => ({
                    rfqItemId: line.rfqItemId,
                    quantity: quantityMap[line.rfqItemId] ?? 1,
                    unitPriceMinor: toMinorUnits(
                        Number(line.unitPrice),
                        minorUnit,
                    ),
                })),
            };
            await reviseQuote.mutateAsync({
                quoteId: quote.id,
                rfqId: quote.rfqId,
                payload,
            });
            publishToast({
                variant: 'success',
                title: 'Revision submitted',
                description: 'New revision is live for the buyer.',
            });
            form.setValue('revisionNote', '');
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const handleWithdraw = async (reason: string) => {
        try {
            await withdrawQuote.mutateAsync({
                quoteId: quote.id,
                rfqId: quote.rfqId,
                reason,
            });
            publishToast({
                variant: 'success',
                title: 'Quote withdrawn',
                description: 'The buyer saw your reason immediately.',
            });
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
        processing:
            updateLine.isPending ||
            submitQuote.isPending ||
            reviseQuote.isPending ||
            withdrawQuote.isPending ||
            updateQuoteDraft.isPending,
        locked: isReadOnly,
    });

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Edit quote · RFQ {rfqNumber}</title>
            </Helmet>

            <PlanUpgradeBanner />

            {rfqDetailQuery.isFetched && responseWindowClosed ? (
                <Alert variant="warning">
                    <AlertTitle>RFQ closed for responses</AlertTitle>
                    <AlertDescription>
                        {deadlineLabel
                            ? `The buyer deadline passed on ${deadlineLabel}. Reach out to request an extension before editing.`
                            : 'This RFQ is no longer open, so quote edits and submissions are locked.'}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="flex flex-col gap-6 lg:flex-row">
                <div className="flex-1 space-y-6">
                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>1. Company & contact</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <DetailRow label="Company">
                                {state.company?.name ?? 'Supplier company'}
                            </DetailRow>
                            <DetailRow label="Contact">
                                {state.user?.name ?? state.user?.email ?? '—'}
                            </DetailRow>
                            <DetailRow label="Email">
                                {state.user?.email ?? '—'}
                            </DetailRow>
                            <DetailRow label="Status">
                                <QuoteStatusBadge status={quote.status} />
                            </DetailRow>
                            <DetailRow label="Revision">
                                Rev {quote.revisionNo ?? 1}
                            </DetailRow>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>2. Quote currency & summary</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="edit-quote-currency">
                                    Currency
                                </Label>
                                <Select
                                    disabled={isReadOnly}
                                    value={
                                        watchedCurrency ??
                                        quote.currency ??
                                        currencyOptions[0]?.value ??
                                        'USD'
                                    }
                                    onValueChange={(value) =>
                                        form.setValue('currency', value, {
                                            shouldDirty: true,
                                        })
                                    }
                                >
                                    <SelectTrigger id="edit-quote-currency">
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {currencyOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-lead-time">
                                    Overall lead time (days)
                                </Label>
                                <Input
                                    id="edit-lead-time"
                                    type="number"
                                    min={0}
                                    step="1"
                                    placeholder="Optional"
                                    disabled={isReadOnly}
                                    {...form.register('leadTimeDays')}
                                />
                                {form.formState.errors.leadTimeDays ? (
                                    <p className="text-xs text-destructive">
                                        {
                                            form.formState.errors.leadTimeDays
                                                .message
                                        }
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-incoterm">
                                    Incoterm (optional)
                                </Label>
                                <Input
                                    id="edit-incoterm"
                                    placeholder="FOB Shenzhen"
                                    disabled={isReadOnly}
                                    {...form.register('incoterm')}
                                />
                                {form.formState.errors.incoterm ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.incoterm.message}
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-payment-terms">
                                    Payment terms (optional)
                                </Label>
                                <Input
                                    id="edit-payment-terms"
                                    placeholder="Net 30"
                                    disabled={isReadOnly}
                                    {...form.register('paymentTerms')}
                                />
                                {form.formState.errors.paymentTerms ? (
                                    <p className="text-xs text-destructive">
                                        {
                                            form.formState.errors.paymentTerms
                                                .message
                                        }
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-min-order">
                                    Minimum order quantity
                                </Label>
                                <Input
                                    id="edit-min-order"
                                    type="number"
                                    min={1}
                                    step="1"
                                    placeholder="Optional"
                                    disabled={isReadOnly}
                                    {...form.register('minOrderQty')}
                                />
                                {form.formState.errors.minOrderQty ? (
                                    <p className="text-xs text-destructive">
                                        {
                                            form.formState.errors.minOrderQty
                                                .message
                                        }
                                    </p>
                                ) : null}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="edit-quote-note">
                                    Buyer-facing note (optional)
                                </Label>
                                <Textarea
                                    id="edit-quote-note"
                                    rows={3}
                                    placeholder="Share payment terms, included tooling, or other assumptions."
                                    disabled={isReadOnly}
                                    {...form.register('note')}
                                />
                                {form.formState.errors.note ? (
                                    <p className="text-xs text-destructive">
                                        {form.formState.errors.note.message}
                                    </p>
                                ) : null}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>3. Line pricing</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <QuoteLineEditor
                                form={form}
                                rfqLines={rfqLines}
                                currency={
                                    watchedCurrency ?? quote.currency ?? 'USD'
                                }
                                disabled={isReadOnly}
                                enableTaxCodes={!isSupplierPersona}
                            />
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>4. Attachments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <QuoteAttachmentsInput
                                form={form}
                                entity="quote"
                                entityId={quote.id}
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
                                <Label htmlFor="revision-note">
                                    Explain what changed
                                </Label>
                                <Textarea
                                    id="revision-note"
                                    rows={3}
                                    placeholder="Example: Updated unit prices based on clarified tolerances."
                                    disabled={
                                        reviseQuote.isPending || isReadOnly
                                    }
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
                                <p className="text-xs text-muted-foreground uppercase">
                                    RFQ
                                </p>
                                <p className="text-base font-semibold">
                                    {rfqNumber}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground uppercase">
                                    Working total
                                </p>
                                <MoneyCell
                                    amountMinor={reviewTotals.totalMinor}
                                    currency={watchedCurrency ?? quote.currency}
                                />
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground uppercase">
                                    Minimum order qty
                                </p>
                                <p className="text-sm font-medium">
                                    {watchedMinOrderQty &&
                                    String(watchedMinOrderQty).length
                                        ? watchedMinOrderQty
                                        : '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground uppercase">
                                    Line progress
                                </p>
                                <p className="text-sm">
                                    {reviewTotals.completedLines}/
                                    {watchedLines?.length ?? 0} complete
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-sidebar-border/60">
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {actionButtons}
                        </CardContent>
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

function buildFormValuesFromQuote(
    quote: Quote,
    rfqLines: RfqItem[],
    minorUnit: number,
): SupplierQuoteFormValues {
    const rfqQuantities = mapLineQuantity(rfqLines, quote.items ?? []);
    return {
        currency: quote.currency,
        minOrderQty: quote.minOrderQty != null ? String(quote.minOrderQty) : '',
        note: quote.note ?? '',
        incoterm: quote.incoterm ?? '',
        paymentTerms: quote.paymentTerms ?? '',
        leadTimeDays:
            quote.leadTimeDays != null ? String(quote.leadTimeDays) : '',
        revisionNote: '',
        attachments: mapQuoteAttachments(quote.attachments),
        lines:
            quote.items?.map((item) => ({
                rfqItemId: String(item.rfqItemId),
                quantity:
                    item.quantity ?? rfqQuantities[String(item.rfqItemId)] ?? 1,
                unitPrice: formatMinorToMajor(item.unitPriceMinor, minorUnit),
                leadTimeDays:
                    item.leadTimeDays != null ? String(item.leadTimeDays) : '',
                note: item.note ?? '',
                taxCodeIds:
                    item.taxes
                        ?.map((tax) =>
                            typeof tax.taxCodeId === 'number'
                                ? String(tax.taxCodeId)
                                : '',
                        )
                        .filter((value) => value.length > 0) ?? [],
            })) ?? [],
    };
}

function mapQuoteAttachments(
    attachments?: Quote['attachments'] | null,
): DocumentAttachment[] {
    if (!attachments || attachments.length === 0) {
        return [];
    }

    return attachments.reduce<DocumentAttachment[]>((acc, attachment) => {
        const idCandidate = Number(
            (attachment as { id?: string | number }).id ?? 0,
        );

        if (!Number.isFinite(idCandidate) || idCandidate <= 0) {
            return acc;
        }

        const rawSize =
            (attachment as { size_bytes?: number; sizeBytes?: number })
                .size_bytes ??
            (attachment as { sizeBytes?: number }).sizeBytes ??
            0;

        acc.push({
            id: idCandidate,
            filename:
                (attachment as { filename?: string }).filename ?? 'Attachment',
            mime:
                (attachment as { mime?: string }).mime ??
                'application/octet-stream',
            sizeBytes: Number(rawSize) || 0,
        });

        return acc;
    }, []);
}

function mapLineQuantity(
    lines: RfqItem[],
    fallbackItems: QuoteItem[],
): Record<string, number> {
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

function inferMinorUnit(settings?: {
    pricingCurrency?: { minorUnit?: number };
    baseCurrency?: { minorUnit?: number };
}) {
    return (
        settings?.pricingCurrency?.minorUnit ??
        settings?.baseCurrency?.minorUnit ??
        null
    );
}

function inferCurrencyOptions(
    settings?: {
        baseCurrency?: { code?: string; name?: string };
        pricingCurrency?: { code?: string; name?: string };
    },
    fallbackCode?: string | null,
) {
    const options = new Map<string, string>();

    if (settings?.pricingCurrency?.code) {
        options.set(
            settings.pricingCurrency.code,
            `${settings.pricingCurrency.code} · ${settings.pricingCurrency.name ?? 'Pricing currency'}`,
        );
    }

    if (settings?.baseCurrency?.code) {
        options.set(
            settings.baseCurrency.code,
            `${settings.baseCurrency.code} · ${settings.baseCurrency.name ?? 'Base currency'}`,
        );
    }

    if (fallbackCode && !options.has(fallbackCode)) {
        options.set(fallbackCode, `${fallbackCode} · Selected currency`);
    }

    if (options.size === 0) {
        options.set('USD', 'USD · United States Dollar');
    }

    return Array.from(options.entries()).map(([value, label]) => ({
        value,
        label,
    }));
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

function formatMinorToMajor(
    value: number | undefined,
    minorUnit: number,
): string {
    if (!value && value !== 0) {
        return '';
    }
    const factor = 10 ** (minorUnit ?? MIN_MINOR_UNIT);
    return (value / factor).toString();
}

function buildDraftUpdatePayload(values: SupplierQuoteFormValues) {
    const attachments = (values.attachments ?? [])
        .map((attachment) => Number(attachment.id))
        .filter((id) => Number.isFinite(id) && id > 0);

    return {
        currency: values.currency,
        min_order_qty:
            values.minOrderQty && values.minOrderQty.length
                ? Number(values.minOrderQty)
                : null,
        lead_time_days:
            values.leadTimeDays && values.leadTimeDays.length
                ? Number(values.leadTimeDays)
                : null,
        incoterm:
            values.incoterm && values.incoterm.length ? values.incoterm : null,
        payment_terms:
            values.paymentTerms && values.paymentTerms.length
                ? values.paymentTerms
                : null,
        note: values.note && values.note.length ? values.note : null,
        attachments: attachments.length ? attachments : undefined,
    } satisfies Record<string, unknown>;
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

    const existingTaxIds = existing.taxes?.map((tax) =>
        typeof tax.taxCodeId === 'number' ? tax.taxCodeId : null,
    );
    if (!haveSameTaxCodeIds(line.taxCodeIds, existingTaxIds)) {
        updates.taxCodeIds = normalizeTaxCodeIds(line.taxCodeIds);
    }

    if (Object.keys(updates).length === 0) {
        return null;
    }

    return updates;
}

function handleQuoteError(
    error: unknown,
    notifyPlanLimit: (notice: {
        code?: string | null;
        message?: string | null;
    }) => void,
) {
    if (error instanceof HttpError) {
        if (error.response.status === 402) {
            notifyPlanLimit({
                code: 'quotes',
                message: 'Upgrade required for quote workflows.',
            });
            publishToast({
                variant: 'destructive',
                title: 'Plan upgrade required',
                description: 'Your workspace plan blocks this action.',
            });
            return;
        }
        if (error.response.status === 403) {
            publishToast({
                variant: 'destructive',
                title: 'Forbidden',
                description: 'You lack permission to modify this quote.',
            });
            return;
        }
    }

    const message =
        error instanceof Error
            ? error.message
            : 'Request failed. Please retry.';
    publishToast({
        variant: 'destructive',
        title: 'Request failed',
        description: message,
    });
}

function buildActionButtons(
    status: QuoteStatusEnum,
    handlers: {
        onSaveDraft: () => void;
        onSubmit: () => void;
        onRevise: () => void;
        onWithdraw: () => void;
        processing: boolean;
        locked?: boolean;
    },
) {
    const buttons: ReactElement[] = [];
    const disabled = handlers.processing || handlers.locked;

    if (status === 'draft') {
        buttons.push(
            <Button
                key="save"
                variant="outline"
                className="w-full"
                onClick={handlers.onSaveDraft}
                disabled={disabled}
            >
                Save draft
            </Button>,
        );
        buttons.push(
            <Button
                key="submit"
                className="w-full"
                onClick={handlers.onSubmit}
                disabled={disabled}
            >
                Submit quote
            </Button>,
        );
    } else if (status === 'submitted') {
        buttons.push(
            <Button
                key="revise"
                className="w-full"
                onClick={handlers.onRevise}
                disabled={disabled}
            >
                Submit revision
            </Button>,
        );
        buttons.push(
            <Button
                key="withdraw"
                variant="destructive"
                className="w-full"
                onClick={handlers.onWithdraw}
                disabled={disabled}
            >
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

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm font-medium text-foreground">{children}</p>
        </div>
    );
}
