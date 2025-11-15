import { useMemo, useState } from 'react';
import { useFieldArray, type UseFormReturn } from 'react-hook-form';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { SupplierQuoteFormValues } from '@/pages/quotes/supplier-quote-schema';
import type { RfqItem } from '@/sdk';

interface QuoteLineEditorProps {
    form: UseFormReturn<SupplierQuoteFormValues>;
    rfqLines: RfqItem[];
    currency: string;
    disabled?: boolean;
}

const DEFAULT_DECIMALS = 4;

export function QuoteLineEditor({ form, rfqLines, currency, disabled = false }: QuoteLineEditorProps) {
    const { control, register, setValue, getValues, formState } = form;
    const { fields } = useFieldArray({ control, name: 'lines' });
    const [bulkLeadTime, setBulkLeadTime] = useState('');
    const [bulkDiscountPercent, setBulkDiscountPercent] = useState('');

    const rfqLineLookup = useMemo(() => {
        return rfqLines.reduce<Record<string, RfqItem>>((acc, item) => {
            acc[String(item.id ?? item.lineNo)] = item;
            return acc;
        }, {});
    }, [rfqLines]);

    const handleApplyBulkLeadTime = () => {
        if (bulkLeadTime.trim().length === 0) {
            return;
        }

        fields.forEach((_, index) => {
            setValue(`lines.${index}.leadTimeDays`, bulkLeadTime, { shouldDirty: true, shouldTouch: true });
        });

        setBulkLeadTime('');
    };

    const handleApplyBulkDiscount = () => {
        const parsed = Number(bulkDiscountPercent);
        if (Number.isNaN(parsed) || parsed <= 0) {
            return;
        }

        const ratio = Math.max(0, Math.min(parsed, 100)) / 100;

        fields.forEach((_, index) => {
            const value = getValues(`lines.${index}.unitPrice`);
            const numeric = Number(value);
            if (Number.isNaN(numeric)) {
                return;
            }
            const discounted = numeric * (1 - ratio);
            setValue(`lines.${index}.unitPrice`, formatDecimal(discounted), { shouldDirty: true, shouldTouch: true });
        });

        setBulkDiscountPercent('');
    };

    if (fields.length === 0) {
        return (
            <Alert>
                <AlertTitle>No RFQ lines</AlertTitle>
                <AlertDescription>The selected RFQ is missing lines. Add RFQ items before composing a quote.</AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-6">
            <Card className="border-sidebar-border/60">
                <CardHeader>
                    <CardTitle>Bulk actions</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="bulk-lead-time">Set lead time (days) for all lines</Label>
                        <div className="flex gap-2">
                            <Input
                                id="bulk-lead-time"
                                type="number"
                                min={0}
                                value={bulkLeadTime}
                                onChange={(event) => setBulkLeadTime(event.target.value)}
                                placeholder="e.g. 21"
                                disabled={disabled}
                            />
                            <Button type="button" variant="secondary" onClick={handleApplyBulkLeadTime} disabled={disabled}>
                                Apply
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">All line lead times update immediately.</p>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="bulk-discount">Apply discount (%) to all unit prices</Label>
                        <div className="flex gap-2">
                            <Input
                                id="bulk-discount"
                                type="number"
                                min={0}
                                max={100}
                                step="0.1"
                                value={bulkDiscountPercent}
                                onChange={(event) => setBulkDiscountPercent(event.target.value)}
                                placeholder="e.g. 5"
                                disabled={disabled}
                            />
                            <Button type="button" variant="secondary" onClick={handleApplyBulkDiscount} disabled={disabled}>
                                Apply
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">Useful for quick early-payment or loyalty discounts.</p>
                    </div>
                </CardContent>
            </Card>

            <div className="space-y-4">
                {fields.map((field, index) => {
                    const rfqLine = rfqLineLookup[String(field.rfqItemId)] ?? null;
                    const lineErrors = formState.errors.lines?.[index];

                    return (
                        <Card key={field.id} className="border-sidebar-border/60">
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                    <span>
                                        Line {rfqLine?.lineNo ?? index + 1}:{' '}
                                        {rfqLine?.partName ?? 'RFQ line'}
                                    </span>
                                    {rfqLine?.targetPrice ? (
                                        <span className="text-xs font-normal text-muted-foreground">
                                            Target • {formatDecimal(rfqLine.targetPrice)} {currency}
                                        </span>
                                    ) : null}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <input type="hidden" {...register(`lines.${index}.rfqItemId` as const)} />
                                <div className="grid gap-4 md:grid-cols-4">
                                    <div className="space-y-2">
                                        <Label htmlFor={`unit-price-${field.id}`}>Unit price ({currency})</Label>
                                        <Input
                                            id={`unit-price-${field.id}`}
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            inputMode="decimal"
                                            disabled={disabled}
                                            {...register(`lines.${index}.unitPrice` as const)}
                                        />
                                        {lineErrors?.unitPrice ? (
                                            <p className="text-xs text-destructive">{lineErrors.unitPrice.message}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`lead-time-${field.id}`}>Lead time (days)</Label>
                                        <Input
                                            id={`lead-time-${field.id}`}
                                            type="number"
                                            min={0}
                                            step="1"
                                            inputMode="numeric"
                                            disabled={disabled}
                                            {...register(`lines.${index}.leadTimeDays` as const)}
                                        />
                                        {lineErrors?.leadTimeDays ? (
                                            <p className="text-xs text-destructive">{lineErrors.leadTimeDays.message}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-1 text-sm text-muted-foreground">
                                        <p className="font-medium text-foreground">Quantity</p>
                                        <p>
                                            {rfqLine?.quantity?.toLocaleString() ?? '—'} {rfqLine?.uom ?? ''}
                                        </p>
                                    </div>
                                    <div className="space-y-1 text-sm text-muted-foreground">
                                        <p className="font-medium text-foreground">Specification</p>
                                        <p>{rfqLine?.spec ?? '—'}</p>
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor={`note-${field.id}`}>Line note (optional)</Label>
                                    <Textarea
                                        id={`note-${field.id}`}
                                        rows={3}
                                        placeholder="Share packaging, tooling, or MOQ considerations."
                                        disabled={disabled}
                                        {...register(`lines.${index}.note` as const)}
                                    />
                                    {lineErrors?.note ? (
                                        <p className="text-xs text-destructive">{lineErrors.note.message}</p>
                                    ) : null}
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}

function formatDecimal(value: number, decimals = DEFAULT_DECIMALS): string {
    if (!Number.isFinite(value)) {
        return '';
    }
    const fixed = value.toFixed(decimals);
    return fixed.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
}