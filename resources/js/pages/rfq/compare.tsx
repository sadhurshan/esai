import { EmptyState, successToast, errorToast } from '@/components/app';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { home, purchaseOrders, rfq as rfqRoutes } from '@/routes';
import { formatCurrencyUSD } from '@/lib/format';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';

import { useAwardQuote } from '@/hooks/api/useAwardQuote';
import { useQuotes } from '@/hooks/api/useQuotes';
import { useRfqItems } from '@/hooks/api/useRfqItems';
import type { BreadcrumbItem } from '@/types';
import type { QuoteLineItem, QuoteSummary, RfqItem } from '@/types/sourcing';

function parseRfqId(url: string): number | null {
    const [path] = url.split('?');
    const segments = path.split('/').filter(Boolean);
    const compareIndex = segments.indexOf('rfq');

    if (compareIndex === -1) {
        return null;
    }

    const potentialId = segments[compareIndex + 1];
    if (!potentialId) {
        return null;
    }

    const parsed = Number.parseInt(potentialId, 10);
    return Number.isNaN(parsed) ? null : parsed;
}

interface SupplierColumn {
    supplierId: number;
    supplierName: string;
    quoteId: number;
    currency: string;
    totalValue: number;
    lineItems: Map<number, QuoteLineItem>;
}

function buildSupplierColumns(items: RfqItem[], quotes: QuoteSummary[]): SupplierColumn[] {
    const quantityMap = new Map<number, number>(items.map((item) => [item.id, item.quantity]));
    const latestQuoteBySupplier = new Map<number, QuoteSummary>();

    quotes.forEach((quote) => {
        const existing = latestQuoteBySupplier.get(quote.supplierId);
        if (!existing || quote.revision >= existing.revision) {
            latestQuoteBySupplier.set(quote.supplierId, quote);
        }
    });

    return Array.from(latestQuoteBySupplier.values()).map((quote) => {
        const lineItems = new Map<number, QuoteLineItem>();
        quote.items.forEach((line) => {
            lineItems.set(line.rfqItemId, line);
        });

        const totalValue = quote.items.reduce((sum, line) => {
            const quantity = quantityMap.get(line.rfqItemId) ?? 0;
            return sum + line.unitPrice * quantity;
        }, 0);

        return {
            supplierId: quote.supplierId,
            supplierName: quote.supplierName || `Supplier ${quote.supplierId}`,
            quoteId: quote.id,
            currency: quote.currency,
            totalValue,
            lineItems,
        };
    });
}

function formatUnitPrice(value: number, currency: string): string {
    const normalized = currency?.toUpperCase?.() ?? 'USD';

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: normalized,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return value.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }
}

export default function RfqCompare() {
    const page = usePage();
    const rfqId = useMemo(() => parseRfqId(page.url), [page.url]);
    const [activeQuoteId, setActiveQuoteId] = useState<number | null>(null);

    const {
        data: rfqItems,
        isLoading: itemsLoading,
        isError: itemsError,
        error: itemsErrorData,
        refetch: refetchItems,
    } = useRfqItems(rfqId ?? 0);

    const {
        data: quotesData,
        isLoading: quotesLoading,
        isError: quotesError,
        error: quotesErrorData,
        refetch: refetchQuotes,
    } = useQuotes(rfqId ?? 0, { per_page: 50 });

    const awardMutation = useAwardQuote(rfqId ?? 0);

    const quotes = useMemo(() => quotesData?.items ?? [], [quotesData]);
    const items = useMemo(() => rfqItems ?? [], [rfqItems]);

    const supplierColumns = useMemo(
        () => buildSupplierColumns(items, quotes).sort((a, b) => a.totalValue - b.totalValue),
        [items, quotes],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Home', href: home().url },
        { title: 'RFQ', href: rfqRoutes.index().url },
        {
            title: rfqId ? `RFQ ${rfqId}` : 'RFQ',
            href: rfqId ? rfqRoutes.show({ id: rfqId }).url : rfqRoutes.index().url,
        },
        { title: 'Compare Quotes', href: rfqId ? rfqRoutes.compare({ id: rfqId }).url : '#' },
    ];

    const handleAward = useCallback(
        async (quoteId: number) => {
            if (!rfqId) {
                return;
            }

            setActiveQuoteId(quoteId);

            try {
                const result = await awardMutation.mutateAsync({ quoteId });
                const poLabel = result.poNumber ? `PO ${result.poNumber}` : `PO #${result.id}`;
                successToast('Purchase order created', poLabel);
                router.visit(purchaseOrders.show({ id: result.id }).url);
            } catch (error) {
                errorToast('Unable to award quote', (error as Error)?.message ?? 'Please try again.');
            } finally {
                setActiveQuoteId(null);
            }
        },
        [awardMutation, rfqId],
    );

    if (rfqId === null) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Quote Comparison" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="RFQ not found"
                        description="The RFQ identifier could not be determined from the URL."
                    />
                </div>
            </AppLayout>
        );
    }

    const loading = itemsLoading || quotesLoading;

    if (itemsError || quotesError) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Quote Comparison" />
                <div className="flex flex-1 items-center justify-center px-4 py-6">
                    <EmptyState
                        title="Unable to load quotes"
                        description={
                            itemsErrorData?.message ??
                            quotesErrorData?.message ??
                            'Please retry fetching RFQ items and supplier quotes.'
                        }
                        ctaLabel="Retry"
                        ctaProps={{
                            onClick: () => {
                                if (itemsError) {
                                    refetchItems();
                                }
                                if (quotesError) {
                                    refetchQuotes();
                                }
                            },
                        }}
                    />
                </div>
            </AppLayout>
        );
    }

    const showEmptyState = !loading && (items.length === 0 || supplierColumns.length === 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Compare Quotes" />
            <div className="flex flex-1 flex-col gap-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold text-foreground">Compare Supplier Quotes</h1>
                        <p className="text-sm text-muted-foreground">
                            Review supplier responses line-by-line to award the quotation that best fits your RFQ.
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={rfqRoutes.show({ id: rfqId }).url}>Back to RFQ</Link>
                    </Button>
                </div>

                <Card className="border-muted/60">
                    <CardHeader>
                        <CardTitle className="text-lg">Quote Comparison</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="space-y-4">
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ) : showEmptyState ? (
                            <EmptyState
                                title={items.length === 0 ? 'No RFQ items available' : 'No submitted quotes yet'}
                                description={
                                    items.length === 0
                                        ? 'Add line items to the RFQ to compare supplier responses.'
                                        : 'Supplier submissions will appear here once quotes are received.'
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-muted/60 text-sm">
                                    <thead className="bg-muted/40">
                                        <tr className="text-left text-xs font-semibold uppercase text-muted-foreground">
                                            <th className="min-w-[220px] px-4 py-3">RFQ Item</th>
                                            {supplierColumns.map((column) => (
                                                <th key={column.supplierId} className="min-w-[220px] px-4 py-3">
                                                    <div className="flex flex-col gap-2">
                                                        <div className="font-medium text-foreground">
                                                            {column.supplierName}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            Total: {formatCurrencyUSD(column.totalValue)}
                                                        </div>
                                                        <Button
                                                            size="sm"
                                                            onClick={() => handleAward(column.quoteId)}
                                                            disabled={awardMutation.isPending}
                                                        >
                                                            {awardMutation.isPending && activeQuoteId === column.quoteId
                                                                ? 'Processing...'
                                                                : 'Accept & Order'}
                                                        </Button>
                                                    </div>
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-muted/40">
                                        {items.map((item) => (
                                            <tr key={item.id} className="align-top">
                                                <td className="px-4 py-4">
                                                    <div className="font-medium text-foreground">
                                                        Line {item.lineNo}: {item.partName}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.quantity.toLocaleString()} {item.uom}
                                                    </div>
                                                </td>
                                                {supplierColumns.map((column) => {
                                                    const line = column.lineItems.get(item.id);

                                                    return (
                                                        <td key={`${column.supplierId}-${item.id}`} className="px-4 py-4">
                                                            {line ? (
                                                                <div className="flex flex-col gap-1">
                                                                    <span className="font-medium text-foreground">
                                                                        {formatUnitPrice(line.unitPrice, column.currency)}
                                                                    </span>
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {line.leadTimeDays > 0
                                                                            ? `${line.leadTimeDays} day${line.leadTimeDays === 1 ? '' : 's'}`
                                                                            : 'Lead time pending'}
                                                                    </span>
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted-foreground">—</span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
