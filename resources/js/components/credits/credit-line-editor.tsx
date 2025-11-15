import { useMemo } from 'react';
import {
    useFieldArray,
    useWatch,
    type ArrayPath,
    type FieldValues,
    type Path,
    type PathValue,
    type UseFormReturn,
} from 'react-hook-form';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MoneyCell } from '@/components/quotes/money-cell';
import { formatCurrencyMinor } from '@/lib/money';

export interface CreditLineFormValue {
    id?: string;
    invoiceLineId: number;
    description?: string | null;
    qtyInvoiced: number;
    qtyAlreadyCredited?: number | null;
    qtyRemaining: number;
    qtyToCredit: number;
    unitPriceMinor: number;
    currency?: string;
    uom?: string | null;
}

export interface CreditLinesFormLike extends FieldValues {
    lines: CreditLineFormValue[];
}

interface CreditLineEditorProps<FormValues extends CreditLinesFormLike> {
    form: UseFormReturn<FormValues>;
    currency?: string | null;
    disabled?: boolean;
}

export function CreditLineEditor<FormValues extends CreditLinesFormLike>({
    form,
    currency,
    disabled = false,
}: CreditLineEditorProps<FormValues>) {
    const { control, register, setValue, formState } = form;
    const linesPath = 'lines' as ArrayPath<FormValues>;
    const { fields: rawFields } = useFieldArray<FormValues, typeof linesPath, 'id'>({ control, name: linesPath });
    const fields = rawFields as Array<(typeof rawFields)[number] & CreditLineFormValue>;
    const watchedLines = useWatch({ control, name: linesPath as Path<FormValues> }) as CreditLineFormValue[] | undefined;

    const totals = useMemo(() => {
        return fields.reduce(
            (acc, field, index) => {
                const watched = watchedLines?.[index];
                const qtyInvoiced = watched?.qtyInvoiced ?? field.qtyInvoiced ?? 0;
                const qtyCredited = watched?.qtyAlreadyCredited ?? field.qtyAlreadyCredited ?? 0;
                const remaining = Math.max(watched?.qtyRemaining ?? field.qtyRemaining ?? qtyInvoiced - qtyCredited, 0);
                const qtyToCredit = watched?.qtyToCredit ?? field.qtyToCredit ?? 0;
                const unitPriceMinor = watched?.unitPriceMinor ?? field.unitPriceMinor ?? 0;

                acc.orderedQty += qtyInvoiced;
                acc.remainingQty += remaining;
                acc.selectedQty += qtyToCredit;
                acc.selectedMinor += Math.max(0, Math.round(qtyToCredit * unitPriceMinor));
                acc.originalMinor += Math.max(0, Math.round(qtyInvoiced * unitPriceMinor));

                return acc;
            },
            { orderedQty: 0, remainingQty: 0, selectedQty: 0, selectedMinor: 0, originalMinor: 0 },
        );
    }, [fields, watchedLines]);

    if (fields.length === 0) {
        return (
            <Alert>
                <AlertTitle>No invoice variances</AlertTitle>
                <AlertDescription>
                    There are no invoice lines with remaining balances. Matching can request another credit if additional
                    discrepancies arise.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-4">
            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Credit summary</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 text-sm md:grid-cols-3">
                    <SummaryItem label="Invoice qty" value={`${formatQty(totals.orderedQty)} units`} />
                    <SummaryItem label="Remaining" value={`${formatQty(totals.remainingQty)} units`} />
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Selected total</p>
                        <p className="text-base font-semibold text-foreground">
                            {formatCurrencyMinor(totals.selectedMinor, currency ?? undefined)}
                        </p>
                        <p className="text-xs text-muted-foreground">{formatQty(totals.selectedQty)} units to credit</p>
                    </div>
                </CardContent>
            </Card>

            <div className="overflow-x-auto rounded-md border border-border/60">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th className="py-2 pl-4 pr-3">Description</th>
                            <th className="py-2 px-3 text-right">Invoiced</th>
                            <th className="py-2 px-3 text-right">Credited</th>
                            <th className="py-2 px-3 text-right">Remaining</th>
                            <th className="py-2 px-3 text-right">Qty to credit</th>
                            <th className="py-2 px-3 text-right">Unit price</th>
                            <th className="py-2 pl-3 pr-4 text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {fields.map((field, index) => {
                            const watched = watchedLines?.[index];
                            const rawError = Array.isArray(formState.errors.lines)
                                ? formState.errors.lines[index]
                                : undefined;
                            const lineErrors = rawError as
                                | Partial<Record<keyof CreditLineFormValue, { message?: string }>>
                                | undefined;
                            const qtyInvoiced = watched?.qtyInvoiced ?? field.qtyInvoiced ?? 0;
                            const qtyCredited = watched?.qtyAlreadyCredited ?? field.qtyAlreadyCredited ?? 0;
                            const remaining = Math.max(
                                watched?.qtyRemaining ?? field.qtyRemaining ?? qtyInvoiced - qtyCredited,
                                0,
                            );
                            const qtyToCredit = watched?.qtyToCredit ?? field.qtyToCredit ?? 0;
                            const unitPriceMinor = watched?.unitPriceMinor ?? field.unitPriceMinor ?? 0;
                            const lineTotalMinor = Math.max(0, Math.round(qtyToCredit * unitPriceMinor));
                            const currencyCode = watched?.currency ?? field.currency ?? currency ?? 'USD';

                            const handleFillRemaining = () => {
                                setValue(
                                    `lines.${index}.qtyToCredit` as Path<FormValues>,
                                    remaining as PathValue<FormValues, Path<FormValues>>,
                                    { shouldDirty: true, shouldTouch: true },
                                );
                            };

                            return (
                                <tr key={field.id ?? `${field.invoiceLineId}-${index}`} className="border-t border-border/60">
                                    <td className="py-3 pl-4 pr-3 align-top text-foreground">
                                        <div className="space-y-1">
                                            <p className="font-medium">{field.description ?? 'Invoice line'}</p>
                                            <p className="text-xs text-muted-foreground">Invoice line #{field.invoiceLineId}</p>
                                            {field.uom ? <Badge variant="outline">{field.uom}</Badge> : null}
                                        </div>
                                    </td>
                                    <td className="py-3 px-3 align-top text-right font-medium">
                                        {formatQty(qtyInvoiced)}
                                    </td>
                                    <td className="py-3 px-3 align-top text-right text-muted-foreground">
                                        {formatQty(qtyCredited)}
                                    </td>
                                    <td className="py-3 px-3 align-top text-right">
                                        <p className="font-medium text-foreground">{formatQty(remaining)}</p>
                                        <p className="text-xs text-muted-foreground">remaining</p>
                                    </td>
                                    <td className="py-3 px-3 align-top">
                                        <div className="space-y-2">
                                            <Label htmlFor={`credit-qty-${field.id ?? index}`} className="sr-only">
                                                Qty to credit
                                            </Label>
                                            <div className="flex gap-2">
                                                <Input
                                                    id={`credit-qty-${field.id ?? index}`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0}
                                                    inputMode="decimal"
                                                    disabled={disabled}
                                                    className="text-right"
                                                    {...register(`lines.${index}.qtyToCredit` as Path<FormValues>, {
                                                        valueAsNumber: true,
                                                    })}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={handleFillRemaining}
                                                    disabled={disabled || remaining <= 0}
                                                >
                                                    Max
                                                </Button>
                                            </div>
                                            {lineErrors?.qtyToCredit?.message ? (
                                                <p className="text-xs text-destructive">{lineErrors.qtyToCredit.message}</p>
                                            ) : (
                                                <p className="text-xs text-muted-foreground">
                                                    ≤ {formatQty(remaining)} {field.uom ?? 'units'}
                                                </p>
                                            )}
                                        </div>
                                    </td>
                                    <td className="py-3 px-3 align-top text-right">
                                        <MoneyCell amountMinor={unitPriceMinor} currency={currencyCode} label="Unit price" />
                                    </td>
                                    <td className="py-3 pl-3 pr-4 align-top text-right">
                                        <MoneyCell amountMinor={lineTotalMinor} currency={currencyCode} label="Line total" />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

interface SummaryItemProps {
    label: string;
    value: string;
}

function SummaryItem({ label, value }: SummaryItemProps) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-base font-semibold text-foreground">{value}</p>
        </div>
    );
}

function formatQty(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '0';
    }

    if (Math.abs(value) >= 1) {
        return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    return value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') || '0';
}
