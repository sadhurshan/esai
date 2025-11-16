import { Fragment, useMemo } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import { QuoteStatusBadge } from './quote-status-badge';
import { MoneyCell } from './money-cell';
import { DeliveryLeadTimeChip } from './delivery-leadtime-chip';
import type { Quote, QuoteItem, RfqItem } from '@/sdk';
import { cn } from '@/lib/utils';
import { useFormatting } from '@/contexts/formatting-context';

interface QuoteCompareTableProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    quotes: Quote[];
    rfqItems?: RfqItem[];
    shortlistedQuoteIds?: Set<string>;
}

interface LineDefinition {
    id: string;
    lineNo?: number;
    label: string;
    spec?: string;
    quantity?: number;
    uom?: string;
}

const FALLBACK_ORDER = Number.MAX_SAFE_INTEGER;

export function QuoteCompareTable({ open, onOpenChange, quotes, rfqItems, shortlistedQuoteIds }: QuoteCompareTableProps) {
    const { formatNumber } = useFormatting();
    const lineDefinitions = useMemo<LineDefinition[]>(() => {
        const map = new Map<string, LineDefinition>();

        rfqItems?.forEach((item) => {
            map.set(item.id, {
                id: item.id,
                lineNo: item.lineNo,
                label: item.partName,
                spec: item.spec,
                quantity: item.quantity,
                uom: item.uom,
            });
        });

        quotes.forEach((quote) => {
            quote.items?.forEach((item) => {
                if (!map.has(item.rfqItemId)) {
                    map.set(item.rfqItemId, {
                        id: item.rfqItemId,
                        label: `Line ${item.rfqItemId}`,
                    });
                }
            });
        });

        return Array.from(map.values()).sort((a, b) => {
            const lineA = a.lineNo ?? FALLBACK_ORDER;
            const lineB = b.lineNo ?? FALLBACK_ORDER;
            if (lineA !== lineB) {
                return lineA - lineB;
            }

            return a.label.localeCompare(b.label);
        });
    }, [quotes, rfqItems]);

    const quoteItemIndex = useMemo(() => {
        const index = new Map<string, Map<string, QuoteItem>>();

        quotes.forEach((quote) => {
            const lineMap = new Map<string, QuoteItem>();
            quote.items?.forEach((item) => {
                lineMap.set(item.rfqItemId, item);
            });
            index.set(quote.id, lineMap);
        });

        return index;
    }, [quotes]);

    const hasEnoughQuotes = quotes.length >= 2;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="max-h-[90vh] w-full overflow-hidden rounded-t-3xl border-t border-sidebar-border/60 bg-background/95 pb-0 sm:max-w-full"
            >
                <SheetHeader className="px-4 pt-4">
                    <SheetTitle>Compare quotes</SheetTitle>
                    <SheetDescription>
                        Review supplier responses side-by-side to validate pricing, lead times, and line-level coverage before
                        awarding.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex flex-col gap-4 overflow-hidden px-4 pb-6">
                    {!hasEnoughQuotes ? (
                        <div className="rounded-xl border border-dashed border-muted-foreground/40 bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground">
                            Select at least two quotes to open the comparison view.
                        </div>
                    ) : (
                        <Fragment>
                            <div className="grid gap-3 md:grid-cols-[minmax(260px,1fr)_repeat(auto-fit,minmax(240px,1fr))]">
                                {quotes.map((quote) => {
                                    const supplierName = quote.supplier?.name ?? `Supplier #${quote.supplierId}`;
                                    const isShortlisted = shortlistedQuoteIds?.has(quote.id);

                                    return (
                                        <div
                                            key={quote.id}
                                            className={cn(
                                                'flex flex-col gap-3 rounded-2xl border border-sidebar-border/60 bg-card/80 p-4 text-sm shadow-sm',
                                                isShortlisted && 'border-emerald-500/70 bg-emerald-50/80',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Supplier</p>
                                                    <p className="text-base font-semibold text-foreground">{supplierName}</p>
                                                </div>
                                                <QuoteStatusBadge status={quote.status} />
                                            </div>
                                            <div className="grid gap-2">
                                                <MoneyCell amountMinor={quote.totalMinor} currency={quote.currency} label="Quote total" />
                                                <div className="flex items-center gap-2">
                                                    <DeliveryLeadTimeChip leadTimeDays={quote.leadTimeDays} />
                                                    <span className="text-xs text-muted-foreground">
                                                        Rev {quote.revisionNo ?? 1}
                                                    </span>
                                                </div>
                                                {isShortlisted ? (
                                                    <Badge variant="secondary" className="w-fit bg-emerald-100 text-emerald-900">
                                                        Shortlisted
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="overflow-auto rounded-2xl border border-sidebar-border/60">
                                <table className="min-w-[720px] table-fixed text-sm">
                                    <thead className="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="w-64 px-4 py-3 font-semibold">RFQ line</th>
                                            {quotes.map((quote) => (
                                                <th key={`header-${quote.id}`} className="px-4 py-3 text-right font-semibold">
                                                    {quote.supplier?.name ?? `Supplier #${quote.supplierId}`}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lineDefinitions.length === 0 ? (
                                            <tr>
                                                <td colSpan={quotes.length + 1} className="px-4 py-6 text-center text-muted-foreground">
                                                    No RFQ lines available for comparison yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            lineDefinitions.map((line) => (
                                                <tr key={line.id} className="border-t border-sidebar-border/40">
                                                    <th
                                                        scope="row"
                                                        className="bg-muted/30 px-4 py-4 text-left text-sm font-medium text-foreground"
                                                    >
                                                        <div className="flex flex-col gap-1">
                                                            <span>
                                                                Line {line.lineNo ?? '—'} · {line.label}
                                                            </span>
                                                            {Number.isFinite(line.quantity) ? (
                                                                <span className="text-xs text-muted-foreground">
                                                                    Qty {formatNumber(line.quantity ?? 0)} {line.uom ?? ''}
                                                                </span>
                                                            ) : null}
                                                            {line.spec ? (
                                                                <span className="text-xs text-muted-foreground">{line.spec}</span>
                                                            ) : null}
                                                        </div>
                                                    </th>
                                                    {quotes.map((quote) => {
                                                        const lineMap = quoteItemIndex.get(quote.id);
                                                        const item = lineMap?.get(line.id);

                                                        if (!item) {
                                                            return (
                                                                <td key={`${quote.id}-${line.id}`} className="px-4 py-4 align-top text-xs text-muted-foreground">
                                                                    No quote
                                                                </td>
                                                            );
                                                        }

                                                        const extendedMinor =
                                                            item.lineTotalMinor ??
                                                            item.lineSubtotalMinor ??
                                                            (item.unitPriceMinor ?? 0) * (item.quantity ?? 1);

                                                        return (
                                                            <td key={`${quote.id}-${line.id}`} className="px-4 py-4 align-top">
                                                                <div className="space-y-3">
                                                                    <MoneyCell
                                                                        amountMinor={item.unitPriceMinor}
                                                                        currency={item.currency ?? quote.currency}
                                                                        label="Unit price"
                                                                    />
                                                                    <MoneyCell
                                                                        amountMinor={extendedMinor}
                                                                        currency={item.currency ?? quote.currency}
                                                                        label="Extended"
                                                                    />
                                                                    <DeliveryLeadTimeChip
                                                                        leadTimeDays={item.leadTimeDays ?? quote.leadTimeDays}
                                                                    />
                                                                    {item.note ? (
                                                                        <p className="text-xs text-muted-foreground">{item.note}</p>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                    <tfoot className="bg-muted/30 text-sm">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-semibold">Totals</th>
                                            {quotes.map((quote) => (
                                                <td key={`total-${quote.id}`} className="px-4 py-3 align-top">
                                                    <MoneyCell amountMinor={quote.totalMinor} currency={quote.currency} label="Grand total" />
                                                </td>
                                            ))}
                                        </tr>
                                        <tr>
                                            <th className="px-4 py-3 text-left font-semibold">Lead time</th>
                                            {quotes.map((quote) => (
                                                <td key={`lead-${quote.id}`} className="px-4 py-3 align-top">
                                                    <DeliveryLeadTimeChip leadTimeDays={quote.leadTimeDays} />
                                                </td>
                                            ))}
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </Fragment>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
