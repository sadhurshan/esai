import { useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import type { PurchaseOrderLine } from '@/types/sourcing';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

interface PoLineTableProps {
    lines: PurchaseOrderLine[];
    currency?: string;
    subtotalMinor?: number;
    taxMinor?: number;
    totalMinor?: number;
    className?: string;
}

const MINOR_FACTOR = 100;

export function PoLineTable({
    lines,
    currency,
    subtotalMinor,
    taxMinor,
    totalMinor,
    className,
}: PoLineTableProps) {
    const locale = typeof navigator !== 'undefined' ? navigator.language : 'en-US';
    const currencyCode = currency ?? 'USD';

    const moneyFormatter = useMemo(() => {
        try {
            return new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: currencyCode,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        } catch (error) {
            void error;
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }
    }, [currencyCode, locale]);

    const formatMoney = (amountMinor?: number, fallbackMajor?: number | null) => {
        if (amountMinor !== undefined && amountMinor !== null) {
            return moneyFormatter.format(amountMinor / MINOR_FACTOR);
        }
        if (fallbackMajor !== undefined && fallbackMajor !== null) {
            return moneyFormatter.format(fallbackMajor);
        }
        return '—';
    };

    const derivedTotals = useMemo(() => {
        if (!lines.length) {
            return { subtotal: 0, tax: 0, total: 0 };
        }

        return lines.reduce(
            (acc, line) => {
                const lineSubtotal = line.lineSubtotalMinor ?? (line.unitPriceMinor ?? 0) * line.quantity;
                const lineTax = line.taxTotalMinor ?? 0;
                const lineTotal = line.lineTotalMinor ?? lineSubtotal + lineTax;

                acc.subtotal += lineSubtotal;
                acc.tax += lineTax;
                acc.total += lineTotal;
                return acc;
            },
            { subtotal: 0, tax: 0, total: 0 },
        );
    }, [lines]);

    const totals = {
        subtotal: subtotalMinor ?? (lines.length ? derivedTotals.subtotal : undefined),
        tax: taxMinor ?? (lines.length ? derivedTotals.tax : undefined),
        total: totalMinor ?? (lines.length ? derivedTotals.total : undefined),
    };

    return (
        <Card className={cn('border-border/70', className)}>
            <CardHeader>
                <CardTitle className="text-lg font-semibold text-foreground">Line items</CardTitle>
                <CardDescription>Review quantities, unit pricing, and delivery promises.</CardDescription>
            </CardHeader>
            <CardContent>
                {lines.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-muted-foreground/40 p-6 text-center text-sm text-muted-foreground">
                        No purchase order lines captured yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] table-fixed text-sm">
                            <thead className="text-xs uppercase tracking-wide text-muted-foreground">
                                <tr className="border-b border-border/60">
                                    <th className="px-3 py-2 text-left">Line</th>
                                    <th className="px-3 py-2 text-left">Description</th>
                                    <th className="px-3 py-2 text-right">Qty</th>
                                    <th className="px-3 py-2 text-left">UoM</th>
                                    <th className="px-3 py-2 text-right">Unit price</th>
                                    <th className="px-3 py-2 text-right">Line total</th>
                                    <th className="px-3 py-2 text-left">Delivery</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line) => (
                                    <tr key={line.id} className="border-b border-border/40 last:border-b-0">
                                        <td className="px-3 py-2 font-medium text-muted-foreground">#{line.lineNo}</td>
                                        <td className="px-3 py-2">
                                            <p className="font-medium text-foreground">{line.description || '—'}</p>
                                        </td>
                                        <td className="px-3 py-2 text-right font-semibold">{line.quantity}</td>
                                        <td className="px-3 py-2 text-left text-muted-foreground">{line.uom}</td>
                                        <td className="px-3 py-2 text-right">
                                            {formatMoney(line.unitPriceMinor, line.unitPrice)}
                                        </td>
                                        <td className="px-3 py-2 text-right font-semibold">
                                            {formatMoney(line.lineTotalMinor, (line.unitPrice ?? 0) * line.quantity)}
                                        </td>
                                        <td className="px-3 py-2 text-left text-muted-foreground">{formatDate(line.deliveryDate)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colSpan={4} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">Subtotal</td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {formatMoney(totals.subtotal)}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td colSpan={4} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">Tax</td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {formatMoney(totals.tax)}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td colSpan={4} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">Grand total</td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {formatMoney(totals.total)}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
