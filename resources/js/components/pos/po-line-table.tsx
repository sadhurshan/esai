import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useFormatting } from '@/contexts/formatting-context';
import { cn } from '@/lib/utils';
import type { PurchaseOrderLine } from '@/types/sourcing';
import { useMemo } from 'react';

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
    const {
        formatMoney,
        formatNumber,
        formatDate,
        currency: tenantCurrency,
    } = useFormatting();
    const currencyCode = currency ?? tenantCurrency;

    const formatMoneyFromMinor = (
        amountMinor?: number | null,
        fallbackMajor?: number | null,
    ) => {
        const majorAmount =
            amountMinor !== undefined && amountMinor !== null
                ? amountMinor / MINOR_FACTOR
                : (fallbackMajor ?? null);
        return formatMoney(majorAmount, { currency: currencyCode });
    };

    const derivedTotals = useMemo(() => {
        if (!lines.length) {
            return { subtotal: 0, tax: 0, total: 0 };
        }

        return lines.reduce(
            (acc, line) => {
                const lineSubtotal =
                    line.lineSubtotalMinor ??
                    (line.unitPriceMinor ?? 0) * line.quantity;
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
        subtotal:
            subtotalMinor ??
            (lines.length ? derivedTotals.subtotal : undefined),
        tax: taxMinor ?? (lines.length ? derivedTotals.tax : undefined),
        total: totalMinor ?? (lines.length ? derivedTotals.total : undefined),
    };

    return (
        <Card className={cn('border-border/70', className)}>
            <CardHeader>
                <CardTitle className="text-lg font-semibold text-foreground">
                    Line items
                </CardTitle>
                <CardDescription>
                    Review quantities, unit pricing, and delivery promises.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {lines.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-muted-foreground/40 p-6 text-center text-sm text-muted-foreground">
                        No purchase order lines captured yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] table-fixed text-sm">
                            <thead className="text-xs tracking-wide text-muted-foreground uppercase">
                                <tr className="border-b border-border/60">
                                    <th className="px-3 py-2 text-left">
                                        Line
                                    </th>
                                    <th className="px-3 py-2 text-left">
                                        Description
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Qty
                                    </th>
                                    <th className="px-3 py-2 text-left">UoM</th>
                                    <th className="px-3 py-2 text-right">
                                        Unit price
                                    </th>
                                    <th className="px-3 py-2 text-right">
                                        Line total
                                    </th>
                                    <th className="px-3 py-2 text-left">
                                        Delivery
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-b border-border/40 last:border-b-0"
                                    >
                                        <td className="px-3 py-2 font-medium text-muted-foreground">
                                            #{line.lineNo}
                                        </td>
                                        <td className="px-3 py-2">
                                            <p className="font-medium text-foreground">
                                                {line.description || '—'}
                                            </p>
                                        </td>
                                        <td className="px-3 py-2 text-right font-semibold">
                                            {formatNumber(line.quantity, {
                                                maximumFractionDigits: 3,
                                            })}
                                        </td>
                                        <td className="px-3 py-2 text-left text-muted-foreground">
                                            {line.uom}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {formatMoneyFromMinor(
                                                line.unitPriceMinor,
                                                line.unitPrice ?? null,
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right font-semibold">
                                            {formatMoneyFromMinor(
                                                line.lineTotalMinor,
                                                typeof line.unitPrice ===
                                                    'number'
                                                    ? line.unitPrice *
                                                          line.quantity
                                                    : null,
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-left text-muted-foreground">
                                            {formatDate(line.deliveryDate)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colSpan={4} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">
                                        Subtotal
                                    </td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {formatMoneyFromMinor(totals.subtotal)}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td colSpan={4} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">
                                        Tax
                                    </td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {formatMoneyFromMinor(totals.tax)}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td colSpan={4} />
                                    <td className="px-3 py-2 text-right text-sm text-muted-foreground">
                                        Grand total
                                    </td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {formatMoneyFromMinor(totals.total)}
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
