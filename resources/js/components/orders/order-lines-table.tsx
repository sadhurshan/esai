import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormatting } from '@/contexts/formatting-context';
import type { SalesOrderLine, SalesOrderTotals } from '@/types/orders';
import { cn } from '@/lib/utils';
import { useMemo } from 'react';

interface OrderLinesTableProps {
    lines: SalesOrderLine[];
    totals?: SalesOrderTotals | null;
    className?: string;
}

const MINOR_FACTOR = 100;

export function OrderLinesTable({ lines, totals, className }: OrderLinesTableProps) {
    const { formatMoney, formatNumber, currency: tenantCurrency } = useFormatting();
    const currencyCode = totals?.currency ?? tenantCurrency;

    const formatMoneyFromMinor = (amountMinor?: number | null, fallbackMajor?: number | null) => {
        const majorAmount =
            amountMinor !== undefined && amountMinor !== null
                ? amountMinor / MINOR_FACTOR
                : fallbackMajor ?? null;
        return formatMoney(majorAmount, { currency: currencyCode });
    };

    const derivedTotals = useMemo(() => {
        if (!lines.length) {
            return { subtotal: 0, tax: 0, total: 0 };
        }

        return lines.reduce(
            (acc, line) => {
                const unitMinor = line.unitPriceMinor ?? null;
                const subtotal = unitMinor !== null ? unitMinor * line.qtyOrdered : 0;
                acc.subtotal += subtotal;
                acc.total += subtotal;
                return acc;
            },
            { subtotal: 0, tax: 0, total: 0 },
        );
    }, [lines]);

    const resolvedTotals = {
        subtotal: totals?.subtotalMinor ?? (lines.length ? derivedTotals.subtotal : undefined),
        tax: totals?.taxMinor ?? (lines.length ? derivedTotals.tax : undefined),
        total: totals?.totalMinor ?? (lines.length ? derivedTotals.total : undefined),
    };

    return (
        <Card className={cn('border-border/70', className)}>
            <CardHeader>
                <CardTitle className="text-lg font-semibold text-foreground">Line items</CardTitle>
                <CardDescription>Quantities, allocations, and shipment progress per SKU.</CardDescription>
            </CardHeader>
            <CardContent>
                {lines.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-muted-foreground/40 p-6 text-center text-sm text-muted-foreground">
                        No sales order lines available yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[820px] table-fixed text-sm">
                            <thead className="text-xs uppercase tracking-wide text-muted-foreground">
                                <tr className="border-b border-border/60">
                                    <th className="px-3 py-2 text-left">Line</th>
                                    <th className="px-3 py-2 text-left">Description</th>
                                    <th className="px-3 py-2 text-right">Ordered</th>
                                    <th className="px-3 py-2 text-right">Allocated</th>
                                    <th className="px-3 py-2 text-right">Shipped</th>
                                    <th className="px-3 py-2 text-left">UoM</th>
                                    <th className="px-3 py-2 text-right">Unit price</th>
                                    <th className="px-3 py-2 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line) => {
                                    const ordered = line.qtyOrdered ?? 0;
                                    const allocated = line.qtyAllocated ?? 0;
                                    const shipped = line.qtyShipped ?? 0;
                                    const lineSubtotalMinor = (line.unitPriceMinor ?? 0) * ordered;

                                    return (
                                        <tr key={line.id} className="border-b border-border/40 last:border-b-0">
                                            <td className="px-3 py-2 font-medium text-muted-foreground">
                                                #{line.soLineId ?? line.id}
                                            </td>
                                            <td className="px-3 py-2">
                                                <p className="font-medium text-foreground">{line.description || '—'}</p>
                                                {line.sku ? (
                                                    <span className="text-xs text-muted-foreground">SKU: {line.sku}</span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold">
                                                {formatNumber(ordered, { maximumFractionDigits: 3 })}
                                            </td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                {formatNumber(allocated, { maximumFractionDigits: 3 })}
                                            </td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">
                                                {formatNumber(shipped, { maximumFractionDigits: 3 })}
                                            </td>
                                            <td className="px-3 py-2 text-left text-muted-foreground">{line.uom ?? '—'}</td>
                                            <td className="px-3 py-2 text-right">
                                                {formatMoneyFromMinor(line.unitPriceMinor)}
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold">
                                                {formatMoneyFromMinor(lineSubtotalMinor)}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colSpan={5} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">Subtotal</td>
                                    <td colSpan={2} className="px-3 py-2 text-right font-semibold">
                                        {formatMoneyFromMinor(resolvedTotals.subtotal)}
                                    </td>
                                </tr>
                                <tr>
                                    <td colSpan={5} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">Tax</td>
                                    <td colSpan={2} className="px-3 py-2 text-right font-semibold">
                                        {formatMoneyFromMinor(resolvedTotals.tax)}
                                    </td>
                                </tr>
                                <tr>
                                    <td colSpan={5} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">Total</td>
                                    <td colSpan={2} className="px-3 py-2 text-right font-semibold">
                                        {formatMoneyFromMinor(resolvedTotals.total)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
