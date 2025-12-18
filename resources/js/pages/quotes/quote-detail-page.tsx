import { useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';
import { Award, Download, FileText, Star, Loader2 } from 'lucide-react';

import { ExportButtons } from '@/components/downloads/export-buttons';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { QuoteStatusBadge } from '@/components/quotes/quote-status-badge';
import { MoneyCell } from '@/components/quotes/money-cell';
import { DeliveryLeadTimeChip } from '@/components/quotes/delivery-leadtime-chip';
import { useAuth } from '@/contexts/auth-context';
import { useQuote } from '@/hooks/api/quotes/use-quote';
import { useQuoteShortlistMutation } from '@/hooks/api/quotes/use-shortlist-mutation';
import { useTaxCodes } from '@/hooks/api/use-tax-codes';
import type { Quote, QuoteAttachmentsInner, QuoteItem, QuoteRevision, TaxCode } from '@/sdk';
import { publishToast } from '@/components/ui/use-toast';
import { useFormatting } from '@/contexts/formatting-context';

interface TimelineEntry {
    id: string;
    label: string;
    description?: string;
    timestamp?: Date;
    status?: string;
}

const TIMELINE_LABELS: Record<string, string> = {
    submitted: 'Quote submitted',
    revision: 'Revision submitted',
    withdrawn: 'Quote withdrawn',
};

export function QuoteDetailPage() {
    const { formatDate } = useFormatting();
    const { quoteId } = useParams<{ quoteId: string }>();
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const quotesFeatureEnabled = hasFeature('quotes_enabled');
    const canAccessQuotes = !featureFlagsLoaded || quotesFeatureEnabled;

    const quoteQuery = useQuote(quoteId, { enabled: Boolean(quoteId) && canAccessQuotes });
    const quote = quoteQuery.data?.quote;
    const shortlistMutation = useQuoteShortlistMutation();
    const taxCodesQuery = useTaxCodes(undefined, { enabled: Boolean(quoteId) && canAccessQuotes });
    const taxCodeLookup = useMemo(() => {
        const lookup = new Map<number, TaxCode>();
        (taxCodesQuery.items ?? []).forEach((taxCode) => {
            lookup.set(taxCode.id, taxCode);
        });
        return lookup;
    }, [taxCodesQuery.items]);

    const supplierName = getSupplierName(quote);
    const shortlisted = Boolean(quote?.isShortlisted);
    const revisions = quote?.revisions ?? [];
    const attachments = quote?.attachments ?? [];
    const lines = quote?.items ?? [];

    const timelineEntries = useMemo<TimelineEntry[]>(() => {
        if (!quote) {
            return [];
        }

        const entries: TimelineEntry[] = [];

        if (quote.submittedAt) {
            entries.push({
                id: 'submitted',
                label: TIMELINE_LABELS.submitted,
                timestamp: quote.submittedAt,
                description: `Revision ${quote.revisionNo ?? 1}`,
                status: quote.status,
            });
        }

        (quote.revisions ?? []).forEach((revision) => {
            entries.push({
                id: `revision-${revision.id}`,
                label: `${TIMELINE_LABELS.revision} · Rev ${revision.revisionNo}`,
                timestamp: revision.submittedAt,
                description: revision.note ?? undefined,
                status: revision.status,
            });
        });

        if (quote.withdrawnAt) {
            entries.push({
                id: 'withdrawn',
                label: TIMELINE_LABELS.withdrawn,
                timestamp: quote.withdrawnAt,
                description: quote.withdrawReason ?? undefined,
                status: 'withdrawn',
            });
        }

        return entries.sort((a, b) => {
            const aTime = a.timestamp ? new Date(a.timestamp).getTime() : 0;
            const bTime = b.timestamp ? new Date(b.timestamp).getTime() : 0;
            return aTime - bTime;
        });
    }, [quote]);

    const handleShortlistToggle = () => {
        if (!quote || shortlistMutation.isPending) {
            return;
        }

        const shouldShortlist = !quote.isShortlisted;
        shortlistMutation.mutate(
            { quoteId: quote.id, shortlist: shouldShortlist, rfqId: quote.rfqId },
            {
                onSuccess: (updated) => {
                    publishToast({
                        variant: 'success',
                        title: shouldShortlist ? 'Quote shortlisted' : 'Shortlist updated',
                        description: shouldShortlist
                            ? `${getSupplierName(updated)} is now on your shortlist.`
                            : `${getSupplierName(updated)} was removed from your shortlist.`,
                    });
                    if (typeof window !== 'undefined') {
                        window.location.reload();
                    }
                },
                onError: (error) => {
                    publishToast({
                        variant: 'destructive',
                        title: 'Unable to update shortlist',
                        description:
                            error instanceof Error ? error.message : 'Please check your connection and try again.',
                    });
                },
            },
        );
    };

    const handleLaunchAwardReview = () => {
        if (!quote?.rfqId) {
            publishToast({
                variant: 'destructive',
                title: 'Unable to open awards',
                description: 'This quote is missing its RFQ reference.',
            });
            return;
        }

        navigate(`/app/rfqs/${quote.rfqId}/awards`, {
            state: {
                quoteIds: [String(quote.id)],
                source: 'quoteDetail',
            },
        });
    };


    if (featureFlagsLoaded && !quotesFeatureEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quotes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Quotes unavailable on current plan"
                    description="Upgrade your workspace plan to review supplier quotes."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    if (!quoteId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quote detail</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Quote not specified"
                    description="Select a quote from the RFQ quotes list to continue."
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
                    <title>Quote detail</title>
                </Helmet>
                <PlanUpgradeBanner />
                <QuoteDetailSkeleton />
            </div>
        );
    }

    if (quoteQuery.isError) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quote detail</title>
                </Helmet>
                <PlanUpgradeBanner />
                <Alert variant="destructive">
                    <AlertTitle>Unable to load quote</AlertTitle>
                    <AlertDescription>
                        We hit an issue retrieving the quote. Please refresh or navigate back to retry.
                    </AlertDescription>
                </Alert>
            </div>
        );
    }

    if (!quote) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quote detail</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Quote unavailable"
                    description="This quote could not be found. It may have been deleted or you may lack access."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="Back to quotes"
                    ctaProps={{ onClick: () => navigate(-1) }}
                />
            </div>
        );
    }

    const title = `${supplierName} · Quote ${quote.id}`;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>{title}</title>
            </Helmet>

            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Quote · RFQ {quote.rfqId}</p>
                    <h1 className="text-2xl font-semibold text-foreground">{supplierName}</h1>
                    <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <QuoteStatusBadge status={quote.status} />
                        <span>Revision {quote.revisionNo ?? 1}</span>
                        {quote.submittedAt ? <span>Submitted {formatDate(quote.submittedAt)}</span> : null}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant={shortlisted ? 'secondary' : 'outline'}
                        size="sm"
                        disabled={shortlistMutation.isPending}
                        onClick={handleShortlistToggle}
                    >
                        {shortlistMutation.isPending ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                            <Star className={shortlisted ? 'h-4 w-4 fill-current' : 'h-4 w-4'} />
                        )}
                        {shortlisted ? 'Shortlisted' : 'Shortlist'}
                    </Button>
                    <Button type="button" size="sm" onClick={handleLaunchAwardReview}>
                        <Award className="mr-2 h-4 w-4" /> Award this quote
                    </Button>
                    <ExportButtons
                        documentType="quote"
                        documentId={quote.id}
                        reference={`Quote #${quote.id}`}
                    />
                </div>
            </div>

            <div className="grid gap-4 rounded-2xl border border-sidebar-border/60 bg-card/60 p-4 md:grid-cols-3">
                <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Quote total</p>
                    <MoneyCell amountMinor={quote.totalMinor} currency={quote.currency} />
                </div>
                <div className="space-y-2">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Lead time</p>
                    <DeliveryLeadTimeChip leadTimeDays={quote.leadTimeDays} />
                </div>
                <div className="space-y-2">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Min order quantity</p>
                    <p className="text-base font-semibold text-foreground">{quote.minOrderQty ?? '—'}</p>
                </div>
            </div>

            <Tabs defaultValue="overview" className="space-y-4">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="lines">Lines</TabsTrigger>
                    <TabsTrigger value="attachments">Attachments</TabsTrigger>
                    <TabsTrigger value="revisions">Revisions</TabsTrigger>
                    <TabsTrigger value="timeline">Timeline</TabsTrigger>
                </TabsList>

                <TabsContent value="overview">
                    <QuoteOverviewCard quote={quote} />
                </TabsContent>
                <TabsContent value="lines">
                    <QuoteLinesTable items={lines} currency={quote.currency} taxCodes={taxCodeLookup} />
                </TabsContent>
                <TabsContent value="attachments">
                    <QuoteAttachmentsPanel attachments={attachments} />
                </TabsContent>
                <TabsContent value="revisions">
                    <QuoteRevisionsPanel revisions={revisions} />
                </TabsContent>
                <TabsContent value="timeline">
                    <QuoteTimeline entries={timelineEntries} />
                </TabsContent>
            </Tabs>
        </div>
    );
}

function QuoteOverviewCard({ quote }: { quote: Quote }) {
    const { formatDate } = useFormatting();
    return (
        <Card className="border-sidebar-border/60 bg-card/80">
            <CardHeader>
                <CardTitle>Summary</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-6 md:grid-cols-2">
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Currency</p>
                    <p className="text-base font-semibold text-foreground">{quote.currency}</p>
                </div>
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Submitted at</p>
                    <p className="text-base font-semibold text-foreground">{formatDate(quote.submittedAt)}</p>
                </div>
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Subtotal</p>
                    <MoneyCell amountMinor={quote.subtotalMinor ?? quote.totalMinor} currency={quote.currency} />
                </div>
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Tax</p>
                    <MoneyCell amountMinor={quote.taxAmountMinor} currency={quote.currency} />
                </div>
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Notes</p>
                    <p className="text-sm text-muted-foreground">{quote.note ?? '—'}</p>
                </div>
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Submitted by</p>
                    <p className="text-base font-semibold text-foreground">{quote.submittedBy ? `User #${quote.submittedBy}` : '—'}</p>
                </div>
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Incoterm</p>
                    <p className="text-base font-semibold text-foreground">{quote.incoterm ?? '—'}</p>
                </div>
                <div className="space-y-1 text-sm">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Payment terms</p>
                    <p className="text-base font-semibold text-foreground">{quote.paymentTerms ?? '—'}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function QuoteLinesTable({ items, currency, taxCodes }: { items: QuoteItem[]; currency?: string | null; taxCodes?: Map<number, TaxCode> }) {
    const { formatNumber } = useFormatting();
    if (items.length === 0) {
        return (
            <EmptyState
                title="No quoted lines"
                description="Supplier has not provided line-level pricing."
                icon={<FileText className="h-10 w-10 text-muted-foreground" />}
            />
        );
    }

    return (
        <div className="overflow-hidden rounded-2xl border border-sidebar-border/60">
            <table className="min-w-full table-fixed text-sm">
                <thead className="bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th className="px-4 py-3 font-semibold">RFQ line</th>
                        <th className="px-4 py-3 font-semibold">Quantity</th>
                        <th className="px-4 py-3 font-semibold">Unit price</th>
                        <th className="px-4 py-3 font-semibold">Extended</th>
                        <th className="px-4 py-3 font-semibold">Lead time</th>
                        <th className="px-4 py-3 font-semibold">Taxes</th>
                        <th className="px-4 py-3 font-semibold">Notes</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-sidebar-border/40">
                    {items.map((item) => {
                        const extendedMinor =
                            item.lineTotalMinor ??
                            item.lineSubtotalMinor ??
                            (item.unitPriceMinor ?? 0) * (item.quantity ?? 1);

                        return (
                            <tr key={item.id} className="bg-background/80">
                                <td className="px-4 py-4 align-top text-sm font-medium text-foreground">
                                    Line {item.rfqItemId}
                                </td>
                                <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                                    {typeof item.quantity === 'number'
                                        ? formatNumber(item.quantity, { maximumFractionDigits: 3 })
                                        : '—'}
                                </td>
                                <td className="px-4 py-4 align-top">
                                    <MoneyCell amountMinor={item.unitPriceMinor} currency={item.currency ?? currency} label="" />
                                </td>
                                <td className="px-4 py-4 align-top">
                                    <MoneyCell amountMinor={extendedMinor} currency={item.currency ?? currency} label="" />
                                </td>
                                <td className="px-4 py-4 align-top">
                                    <DeliveryLeadTimeChip leadTimeDays={item.leadTimeDays} />
                                </td>
                                <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                                    {item.taxes && item.taxes.length > 0 ? (
                                        <div className="space-y-2">
                                            {item.taxes.map((tax, index) => {
                                                const taxCode = tax.taxCodeId && taxCodes ? taxCodes.get(tax.taxCodeId) : undefined;
                                                const codeLabel = taxCode?.code ?? (tax.taxCodeId ? `Tax ${tax.taxCodeId}` : 'Custom tax');
                                                const nameLabel = taxCode?.name ?? '—';

                                                return (
                                                    <div
                                                        key={tax.id ?? `${item.id}-tax-${tax.taxCodeId ?? index}`}
                                                        className="rounded-xl border border-sidebar-border/40 bg-muted/20 px-3 py-2"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2 text-xs">
                                                            <Badge variant="outline" className="font-semibold">
                                                                {codeLabel}
                                                            </Badge>
                                                            {typeof tax.ratePercent === 'number' ? (
                                                                <span className="text-muted-foreground">{tax.ratePercent}%</span>
                                                            ) : null}
                                                        </div>
                                                        <p className="mt-1 text-[11px] text-muted-foreground">{nameLabel}</p>
                                                        {typeof tax.amountMinor === 'number' ? (
                                                            <MoneyCell
                                                                amountMinor={tax.amountMinor}
                                                                currency={item.currency ?? currency}
                                                                label=""
                                                                className="mt-1"
                                                            />
                                                        ) : null}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        <span>—</span>
                                    )}
                                </td>
                                <td className="px-4 py-4 align-top text-sm text-muted-foreground">{item.note ?? '—'}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function QuoteAttachmentsPanel({ attachments }: { attachments: QuoteAttachmentsInner[] }) {
    if (attachments.length === 0) {
        return (
            <EmptyState
                title="No attachments"
                description="Supplier did not include supporting documents with this quote."
                icon={<FileText className="h-10 w-10 text-muted-foreground" />}
            />
        );
    }

    return (
        <div className="space-y-3">
            {attachments.map((attachment) => (
                <div
                    key={attachment.id ?? attachment.path ?? attachment.filename}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border/60 bg-card/70 px-4 py-3"
                >
                    <div>
                        <p className="text-sm font-medium text-foreground">{attachment.filename ?? 'Attachment'}</p>
                        <p className="text-xs text-muted-foreground">
                            {attachment.mime ?? 'Unknown type'} · {formatFileSize(attachment.sizeBytes)}
                        </p>
                    </div>
                    {attachment.path ? (
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={attachment.path}
                                download={attachment.filename ?? undefined}
                                rel="noreferrer"
                            >
                                <Download className="h-4 w-4" />
                                Download
                            </a>
                        </Button>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

function QuoteRevisionsPanel({ revisions }: { revisions: QuoteRevision[] }) {
    const { formatDate } = useFormatting();
    if (revisions.length === 0) {
        return (
            <EmptyState
                title="No revisions"
                description="Supplier has not submitted revisions for this quote yet."
                icon={<FileText className="h-10 w-10 text-muted-foreground" />}
            />
        );
    }

    return (
        <div className="space-y-4">
            {revisions.map((revision) => (
                <div
                    key={revision.id}
                    className="rounded-2xl border border-sidebar-border/60 bg-card/70 p-4"
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge variant="secondary">Rev {revision.revisionNo}</Badge>
                        <QuoteStatusBadge status={revision.status} />
                        <span className="text-sm text-muted-foreground">
                            {formatDate(revision.submittedAt, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            })}
                        </span>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">{revision.note ?? 'No notes provided.'}</p>
                    {/* TODO: clarify with spec how to highlight total or line changes once the API returns revision diff metadata. */}
                </div>
            ))}
        </div>
    );
}

function QuoteTimeline({ entries }: { entries: TimelineEntry[] }) {
    const { formatDate } = useFormatting();
    if (entries.length === 0) {
        return (
            <EmptyState
                title="No timeline events"
                description="Quote history is unavailable."
                icon={<FileText className="h-10 w-10 text-muted-foreground" />}
            />
        );
    }

    return (
        <div className="space-y-4">
            {entries.map((entry) => (
                <div key={entry.id} className="flex gap-4">
                    <div className="flex flex-col items-center">
                            <span className="text-xs text-muted-foreground">{formatDate(entry.timestamp)}</span>
                        <span className="mt-1 h-full w-px bg-sidebar-border/60" aria-hidden="true" />
                    </div>
                    <div className="flex-1 rounded-xl border border-sidebar-border/60 bg-card/70 px-4 py-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-semibold text-foreground">{entry.label}</p>
                            {entry.status ? <QuoteStatusBadge status={entry.status} /> : null}
                        </div>
                        {entry.description ? (
                            <p className="mt-1 text-sm text-muted-foreground">{entry.description}</p>
                        ) : null}
                    </div>
                </div>
            ))}
        </div>
    );
}

function QuoteDetailSkeleton() {
    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-8 w-64" />
                <Skeleton className="h-4 w-48" />
            </div>
            <div className="grid gap-4 md:grid-cols-3">
                <Skeleton className="h-28 w-full" />
                <Skeleton className="h-28 w-full" />
                <Skeleton className="h-28 w-full" />
            </div>
            <Skeleton className="h-10 w-72" />
            <Skeleton className="h-[320px] w-full" />
        </div>
    );
}

function getSupplierName(quote?: Quote | null): string {
    if (!quote) {
        return 'Supplier quote';
    }

    return quote.supplier?.name ?? `Supplier #${quote.supplierId}`;
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

