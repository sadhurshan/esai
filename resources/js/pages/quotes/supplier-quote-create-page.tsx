import { zodResolver } from '@hookform/resolvers/zod';
import { FileText, Loader2 } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import { Helmet } from 'react-helmet-async';
import { useForm, useWatch } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';

import { DocumentNumberPreview } from '@/components/documents/document-number-preview';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { MoneyCell } from '@/components/quotes/money-cell';
import { QuoteAttachmentsInput } from '@/components/quotes/quote-attachments-input';
import { QuoteLineEditor } from '@/components/quotes/quote-line-editor';
import { QuoteStatusBadge } from '@/components/quotes/quote-status-badge';
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
import { useCreateQuote } from '@/hooks/api/quotes/use-create-quote';
import { useSubmitQuote } from '@/hooks/api/quotes/use-submit-quote';
import { useRfq } from '@/hooks/api/rfqs/use-rfq';
import { useRfqLines } from '@/hooks/api/rfqs/use-rfq-lines';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import {
    getRfqDeadlineDate,
    isResponseWindowClosed,
} from '@/lib/rfq-response-window';
import { normalizeTaxCodeIds } from '@/lib/tax-code-helpers';
import type { SupplierQuoteFormValues } from '@/pages/quotes/supplier-quote-schema';
import { supplierQuoteFormSchema } from '@/pages/quotes/supplier-quote-schema';
import type { Quote, RfqItem, SubmitQuoteRequest } from '@/sdk';
import { HttpError, SubmitQuoteRequestStatusEnum } from '@/sdk';

const MIN_MINOR_UNIT = 2;

export function SupplierQuoteCreatePage() {
    const { rfqId } = useParams<{ rfqId: string }>();
    const navigate = useNavigate();
    const { hasFeature, state, notifyPlanLimit, activePersona } = useAuth();
    const supplierRole = state.user?.role === 'supplier';
    const isSupplierPersona = activePersona?.type === 'supplier';
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const quotesEnabled = hasFeature('quotes_enabled') || isSupplierPersona;
    const supplierPortalEnabled =
        hasFeature('supplier_portal_enabled') ||
        supplierRole ||
        isSupplierPersona;
    const canAccessQuotes =
        !featureFlagsLoaded || (quotesEnabled && supplierPortalEnabled);

    const { formatDate } = useFormatting();

    const rfqQuery = useRfq(rfqId, {
        enabled: Boolean(rfqId) && canAccessQuotes,
    });
    const rfqLinesQuery = useRfqLines({ rfqId: rfqId ?? null });
    const moneySettingsQuery = useMoneySettings();

    const createQuote = useCreateQuote();
    const submitQuote = useSubmitQuote();

    const rfqLines = useMemo(
        () => rfqLinesQuery.items ?? [],
        [rfqLinesQuery.items],
    );
    const rfqLineQuantities = useMemo(
        () => mapLineQuantity(rfqLines),
        [rfqLines],
    );

    const currencyOptions = useMemo(
        () => inferCurrencyOptions(moneySettingsQuery.data),
        [moneySettingsQuery.data],
    );
    const defaultCurrency = currencyOptions[0]?.value ?? 'USD';
    const minorUnit = inferMinorUnit(moneySettingsQuery.data) ?? MIN_MINOR_UNIT;

    const form = useForm<SupplierQuoteFormValues>({
        resolver: zodResolver(supplierQuoteFormSchema),
        defaultValues: createDefaultValues([], defaultCurrency),
    });
    const resetSignatureRef = useRef<string | null>(null);

    const watchedLines = useWatch({ control: form.control, name: 'lines' });
    const watchedCurrency = useWatch({
        control: form.control,
        name: 'currency',
    });
    const watchedMinOrderQty = useWatch({
        control: form.control,
        name: 'minOrderQty',
    });

    const linesSignature = useMemo(
        () => JSON.stringify(rfqLines.map((line) => [line.id, line.quantity])),
        [rfqLines],
    );

    useEffect(() => {
        if (rfqLines.length === 0) {
            return;
        }

        const signature = `${rfqId ?? 'new'}-${defaultCurrency}-${linesSignature}`;
        if (resetSignatureRef.current === signature) {
            return;
        }

        form.reset(createDefaultValues(rfqLines, defaultCurrency));
        resetSignatureRef.current = signature;
    }, [defaultCurrency, form, linesSignature, rfqId, rfqLines]);

    const quoteTotals = useMemo(() => {
        return calculateReviewTotals(
            watchedLines,
            rfqLineQuantities,
            minorUnit,
        );
    }, [minorUnit, rfqLineQuantities, watchedLines]);

    const rfq = rfqQuery.data ?? null;
    const rfqDeadlineDate = getRfqDeadlineDate(rfq);
    const responseWindowClosed = isResponseWindowClosed(rfq);
    const deadlineLabel = rfqDeadlineDate ? formatDate(rfqDeadlineDate) : null;

    const handleSaveDraft = async (values: SupplierQuoteFormValues) => {
        if (!rfqId) {
            return;
        }

        try {
            const payload = buildSubmitQuotePayload(values, rfqId, minorUnit);
            const response = await createQuote.mutateAsync({
                ...payload,
                status: SubmitQuoteRequestStatusEnum.Draft,
            });
            publishToast({
                variant: 'success',
                title: 'Draft created',
                description: 'Continue editing and submit when ready.',
            });
            navigate(`/app/supplier/quotes/${response.id}`);
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    const handleSubmitQuote = async (values: SupplierQuoteFormValues) => {
        if (!rfqId) {
            return;
        }

        try {
            const payload = buildSubmitQuotePayload(values, rfqId, minorUnit);
            const response: Quote = await createQuote.mutateAsync(payload);
            await submitQuote.mutateAsync({
                quoteId: response.id,
                rfqId: response.rfqId,
            });
            publishToast({
                variant: 'success',
                title: 'Quote submitted',
                description: 'We notified the buyer and logged this revision.',
            });
            navigate(`/app/supplier/quotes/${response.id}`);
        } catch (error) {
            handleQuoteError(error, notifyPlanLimit);
        }
    };

    if (featureFlagsLoaded && !supplierPortalEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier quotes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Supplier workspace not enabled"
                    description="Ask the buyer to enable supplier portal access to submit quotes online."
                    icon={
                        <FileText className="h-10 w-10 text-muted-foreground" />
                    }
                />
            </div>
        );
    }

    if (!rfqId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier quotes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Select an RFQ"
                    description="Open this page from an RFQ invitation to respond."
                    icon={
                        <FileText className="h-10 w-10 text-muted-foreground" />
                    }
                    ctaLabel="Back to RFQs"
                    ctaProps={{ onClick: () => navigate('/app/rfqs') }}
                />
            </div>
        );
    }

    const isLoading = rfqQuery.isLoading || rfqLinesQuery.isLoading;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Submit quote · RFQ {rfqId}</title>
            </Helmet>

            <PlanUpgradeBanner />

            {!isLoading && responseWindowClosed ? (
                <Alert variant="warning">
                    <AlertTitle>RFQ closed for responses</AlertTitle>
                    <AlertDescription>
                        {deadlineLabel
                            ? `The submission window ended on ${deadlineLabel}. Contact the buyer to request an extension before editing.`
                            : 'This RFQ is no longer open for supplier edits. Contact the buyer if you need the deadline extended.'}
                    </AlertDescription>
                </Alert>
            ) : null}

            {isLoading ? (
                <Card className="border-sidebar-border/60">
                    <CardContent className="flex items-center gap-3 p-8 text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" /> Preparing
                        RFQ context…
                    </CardContent>
                </Card>
            ) : null}

            {!isLoading && rfqQuery.isError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load RFQ</AlertTitle>
                    <AlertDescription>
                        Check the invitation link or try again shortly.
                    </AlertDescription>
                </Alert>
            ) : null}

            {!isLoading ? (
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
                                    {state.user?.name ??
                                        state.user?.email ??
                                        '—'}
                                </DetailRow>
                                <DetailRow label="Email">
                                    {state.user?.email ?? '—'}
                                </DetailRow>
                            </CardContent>
                        </Card>

                        <Card className="border-sidebar-border/60">
                            <CardHeader>
                                <CardTitle>
                                    2. Quote currency & summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="quote-currency">
                                        Currency
                                    </Label>
                                    <Select
                                        disabled={responseWindowClosed}
                                        value={watchedCurrency}
                                        onValueChange={(value) =>
                                            form.setValue('currency', value, {
                                                shouldDirty: true,
                                            })
                                        }
                                    >
                                        <SelectTrigger id="quote-currency">
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
                                    <Label htmlFor="quote-lead">
                                        Overall lead time (days)
                                    </Label>
                                    <Input
                                        id="quote-lead"
                                        type="number"
                                        min={0}
                                        step="1"
                                        placeholder="Optional"
                                        disabled={responseWindowClosed}
                                        {...form.register('leadTimeDays')}
                                    />
                                    {form.formState.errors.leadTimeDays ? (
                                        <p className="text-xs text-destructive">
                                            {
                                                form.formState.errors
                                                    .leadTimeDays.message
                                            }
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="quote-incoterm">
                                        Incoterm (optional)
                                    </Label>
                                    <Input
                                        id="quote-incoterm"
                                        placeholder="FOB Shenzhen"
                                        disabled={responseWindowClosed}
                                        {...form.register('incoterm')}
                                    />
                                    {form.formState.errors.incoterm ? (
                                        <p className="text-xs text-destructive">
                                            {
                                                form.formState.errors.incoterm
                                                    .message
                                            }
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="quote-payment-terms">
                                        Payment terms (optional)
                                    </Label>
                                    <Input
                                        id="quote-payment-terms"
                                        placeholder="Net 30"
                                        disabled={responseWindowClosed}
                                        {...form.register('paymentTerms')}
                                    />
                                    {form.formState.errors.paymentTerms ? (
                                        <p className="text-xs text-destructive">
                                            {
                                                form.formState.errors
                                                    .paymentTerms.message
                                            }
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="quote-moq">
                                        Minimum order quantity
                                    </Label>
                                    <Input
                                        id="quote-moq"
                                        type="number"
                                        min={1}
                                        step="1"
                                        placeholder="Optional"
                                        disabled={responseWindowClosed}
                                        {...form.register('minOrderQty')}
                                    />
                                    {form.formState.errors.minOrderQty ? (
                                        <p className="text-xs text-destructive">
                                            {
                                                form.formState.errors
                                                    .minOrderQty.message
                                            }
                                        </p>
                                    ) : null}
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="quote-note">
                                        Buyer-facing note (optional)
                                    </Label>
                                    <Textarea
                                        id="quote-note"
                                        rows={3}
                                        placeholder="Share payment terms, included tooling, or other assumptions."
                                        disabled={responseWindowClosed}
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
                                        watchedCurrency ?? defaultCurrency
                                    }
                                    disabled={responseWindowClosed}
                                    enableTaxCodes={!isSupplierPersona}
                                />
                            </CardContent>
                        </Card>

                        <Card className="border-sidebar-border/60">
                            <CardHeader>
                                <CardTitle>4. Attachments</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <QuoteAttachmentsInput
                                    form={form}
                                    entity="rfq"
                                    entityId={rfqId}
                                    disabled={responseWindowClosed}
                                />
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="w-full max-w-md space-y-4">
                        <ReviewPanel
                            rfqNumber={rfqQuery.data?.number ?? rfqId}
                            totalMinor={quoteTotals.totalMinor}
                            currency={watchedCurrency ?? defaultCurrency}
                            minOrderQty={watchedMinOrderQty}
                            lineCount={quoteTotals.completedLines}
                            totalLines={watchedLines?.length ?? 0}
                            showNumberPreview={!isSupplierPersona}
                        />

                        <Card className="border-sidebar-border/60">
                            <CardHeader>
                                <CardTitle>Submit</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-full"
                                    disabled={
                                        createQuote.isPending ||
                                        responseWindowClosed
                                    }
                                    onClick={form.handleSubmit(handleSaveDraft)}
                                >
                                    Save draft
                                </Button>
                                <Button
                                    type="button"
                                    className="w-full"
                                    disabled={
                                        createQuote.isPending ||
                                        submitQuote.isPending ||
                                        responseWindowClosed
                                    }
                                    onClick={form.handleSubmit(
                                        handleSubmitQuote,
                                    )}
                                >
                                    {createQuote.isPending ||
                                    submitQuote.isPending
                                        ? 'Submitting…'
                                        : 'Submit quote'}
                                </Button>
                                <p className="text-xs text-muted-foreground">
                                    {responseWindowClosed
                                        ? 'This RFQ is locked. Ask the buyer to extend the deadline before making changes.'
                                        : 'We will email the buyer and show this submission in the RFQ timeline.'}
                                </p>
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            ) : null}
        </div>
    );
}

function createDefaultValues(
    lines: RfqItem[],
    currency: string,
): SupplierQuoteFormValues {
    return {
        currency,
        minOrderQty: '',
        note: '',
        incoterm: '',
        paymentTerms: '',
        leadTimeDays: '',
        revisionNote: '',
        attachments: [],
        lines: lines.map((line) => ({
            rfqItemId: String(line.id),
            quantity: line.quantity,
            unitPrice: '',
            leadTimeDays: '',
            note: '',
            taxCodeIds: [],
        })),
    };
}

function mapLineQuantity(lines: RfqItem[]): Record<string, number> {
    return lines.reduce<Record<string, number>>((acc, line) => {
        acc[String(line.id)] = line.quantity ?? 1;
        return acc;
    }, {});
}

function inferCurrencyOptions(settings?: {
    baseCurrency?: { code?: string; name?: string };
    pricingCurrency?: { code?: string; name?: string };
}) {
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
    if (options.size === 0) {
        options.set('USD', 'USD · United States Dollar');
    }
    return Array.from(options.entries()).map(([value, label]) => ({
        value,
        label,
    }));
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

function calculateReviewTotals(
    lines: SupplierQuoteFormValues['lines'],
    quantityMap: Record<string, number>,
    minorUnit: number,
) {
    if (!lines || lines.length === 0) {
        return { totalMinor: 0, completedLines: 0 };
    }

    const totalMinor = lines.reduce((acc, line) => {
        const qty = quantityMap[line.rfqItemId] ?? 1;
        if (!line.unitPrice || line.unitPrice.trim().length === 0) {
            return acc;
        }
        const unitPrice = Number(line.unitPrice);
        if (Number.isNaN(unitPrice) || Number.isNaN(qty)) {
            return acc;
        }
        const lineMinor = toMinorUnits(unitPrice, minorUnit) * qty;
        return acc + lineMinor;
    }, 0);

    const completedLines = lines.reduce((acc, line) => {
        if (
            line.unitPrice &&
            line.unitPrice.trim().length > 0 &&
            line.leadTimeDays &&
            line.leadTimeDays.trim().length > 0
        ) {
            return acc + 1;
        }
        return acc;
    }, 0);

    return { totalMinor, completedLines };
}

function toMinorUnits(amount: number, minorUnit: number): number {
    const factor = 10 ** (minorUnit ?? MIN_MINOR_UNIT);
    return Math.round(amount * factor);
}

function buildSubmitQuotePayload(
    values: SupplierQuoteFormValues,
    rfqId: string | number,
    minorUnit: number,
): SubmitQuoteRequest {
    const attachmentIds = (values.attachments ?? [])
        .map((attachment) => String(attachment.id))
        .filter((value) => value.length > 0);

    return {
        rfqId: String(rfqId),
        currency: values.currency,
        minOrderQty:
            values.minOrderQty && values.minOrderQty.length
                ? Number(values.minOrderQty)
                : undefined,
        leadTimeDays:
            values.leadTimeDays && values.leadTimeDays.length
                ? Number(values.leadTimeDays)
                : undefined,
        note: values.note && values.note.length ? values.note : undefined,
        incoterm:
            values.incoterm && values.incoterm.length
                ? values.incoterm
                : undefined,
        paymentTerms:
            values.paymentTerms && values.paymentTerms.length
                ? values.paymentTerms
                : undefined,
        attachments: attachmentIds.length ? attachmentIds : undefined,
        items: values.lines.map((line) => {
            const taxCodeIds = normalizeTaxCodeIds(line.taxCodeIds);
            return {
                rfqItemId: line.rfqItemId,
                currency: values.currency,
                leadTimeDays: Number(line.leadTimeDays),
                unitPriceMinor: toMinorUnits(Number(line.unitPrice), minorUnit),
                note: line.note && line.note.length ? line.note : undefined,
                taxCodeIds: taxCodeIds.length ? taxCodeIds : undefined,
            };
        }),
    };
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
                message: 'Quotes require a higher plan.',
            });
            publishToast({
                variant: 'destructive',
                title: 'Plan upgrade required',
                description: 'Current plan cannot submit quotes.',
            });
            return;
        }
        if (error.response.status === 403) {
            publishToast({
                variant: 'destructive',
                title: 'Forbidden',
                description:
                    'You are not allowed to submit quotes for this RFQ.',
            });
            return;
        }
    }

    const message =
        error instanceof Error ? error.message : 'Unable to process quote.';
    publishToast({
        variant: 'destructive',
        title: 'Submission failed',
        description: message,
    });
}

function ReviewPanel({
    rfqNumber,
    totalMinor,
    currency,
    minOrderQty,
    lineCount,
    totalLines,
    showNumberPreview = true,
}: {
    rfqNumber: string | number;
    totalMinor: number;
    currency: string;
    minOrderQty?: string | number | null;
    lineCount: number;
    totalLines: number;
    showNumberPreview?: boolean;
}) {
    return (
        <Card className="border-sidebar-border/60">
            <CardHeader>
                <CardTitle>Review</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div>
                    <p className="text-xs text-muted-foreground uppercase">
                        RFQ
                    </p>
                    <p className="text-base font-semibold">{rfqNumber}</p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground uppercase">
                        Quote total
                    </p>
                    <MoneyCell amountMinor={totalMinor} currency={currency} />
                </div>
                <div>
                    <p className="text-xs text-muted-foreground uppercase">
                        Minimum order qty
                    </p>
                    <p className="text-sm font-medium">
                        {minOrderQty && String(minOrderQty).length
                            ? minOrderQty
                            : '—'}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground uppercase">
                        Line progress
                    </p>
                    <p className="text-sm">
                        {lineCount}/{totalLines} complete
                    </p>
                </div>
                <div className="rounded-lg border border-dashed border-sidebar-border/60 p-4 text-sm text-muted-foreground">
                    <p>Buyers will see this as a new draft until you submit.</p>
                    <div className="mt-3 inline-flex items-center gap-2 rounded-full bg-muted/60 px-3 py-1">
                        <QuoteStatusBadge status="draft" />
                        Draft in progress
                    </div>
                </div>
                {showNumberPreview ? (
                    <DocumentNumberPreview docType="quote" className="w-full" />
                ) : (
                    <div className="rounded-lg border border-dashed border-sidebar-border/60 bg-muted/20 px-4 py-3 text-xs text-muted-foreground">
                        Quote numbers are assigned by the buyer after they
                        review your submission.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
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
