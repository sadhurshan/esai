import { AlertTriangle } from 'lucide-react';
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
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    useFormatting,
    type FormattingContextValue,
} from '@/contexts/formatting-context';

export interface GrnLineFormValue {
    id?: string | number;
    poLineId: number;
    lineNo?: number | null;
    description?: string | null;
    orderedQty: number;
    previouslyReceived?: number | null;
    remainingQty?: number | null;
    qtyReceived: number;
    uom?: string | null;
    notes?: string | null;
}

export interface GrnFormLike extends FieldValues {
    lines: GrnLineFormValue[];
}

interface GrnLineEditorProps<FormValues extends GrnFormLike> {
    form: UseFormReturn<FormValues>;
    disabled?: boolean;
}

export function GrnLineEditor<FormValues extends GrnFormLike>({
    form,
    disabled = false,
}: GrnLineEditorProps<FormValues>) {
    const { control, register, setValue, formState } = form;
    const { formatNumber } = useFormatting();
    const linesPath = 'lines' as ArrayPath<FormValues>;
    const { fields: rawFields } = useFieldArray<
        FormValues,
        typeof linesPath,
        'id'
    >({ control, name: linesPath });
    const fields = rawFields as Array<
        (typeof rawFields)[number] & GrnLineFormValue
    >;
    const watchedLines = useWatch({
        control,
        name: linesPath as Path<FormValues>,
    }) as GrnLineFormValue[] | undefined;

    const hasLinesToReceive = fields.length > 0;

    const orderedTotals = useMemo(() => {
        return fields.reduce(
            (acc, field, index) => {
                const watched = watchedLines?.[index];
                const ordered = watched?.orderedQty ?? field.orderedQty ?? 0;
                const previously =
                    watched?.previouslyReceived ??
                    field.previouslyReceived ??
                    0;
                const planned = watched?.qtyReceived ?? field.qtyReceived ?? 0;

                acc.totalOrdered += ordered;
                acc.totalPlanned += planned;
                acc.totalPreviously += previously;
                return acc;
            },
            { totalOrdered: 0, totalPlanned: 0, totalPreviously: 0 },
        );
    }, [fields, watchedLines]);

    if (!hasLinesToReceive) {
        return (
            <Alert>
                <AlertTitle>No remaining lines</AlertTitle>
                <AlertDescription>
                    This purchase order does not have open quantities. Choose
                    another PO or allow overrides.
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-4">
            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-4 text-sm text-muted-foreground md:grid-cols-3">
                    <div>
                        <p className="text-xs tracking-wide text-foreground/70 uppercase">
                            Ordered
                        </p>
                        <p className="text-base font-semibold text-foreground">
                            {formatQty(
                                orderedTotals.totalOrdered,
                                formatNumber,
                            )}{' '}
                            units
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-foreground/70 uppercase">
                            Received to date
                        </p>
                        <p className="text-base font-semibold text-foreground">
                            {formatQty(
                                orderedTotals.totalPreviously,
                                formatNumber,
                            )}{' '}
                            units
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-foreground/70 uppercase">
                            Planned in this GRN
                        </p>
                        <p className="text-base font-semibold text-foreground">
                            {formatQty(
                                orderedTotals.totalPlanned,
                                formatNumber,
                            )}{' '}
                            units
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div className="space-y-4">
                {fields.map((field, index) => {
                    const lineErrors = Array.isArray(formState.errors.lines)
                        ? formState.errors.lines[index]
                        : undefined;
                    const watched = watchedLines?.[index] ?? field;
                    const ordered =
                        watched?.orderedQty ?? field.orderedQty ?? 0;
                    const previously =
                        watched?.previouslyReceived ??
                        field.previouslyReceived ??
                        0;
                    const remainingFallback = Number.isFinite(
                        ordered - previously,
                    )
                        ? ordered - previously
                        : 0;
                    const remaining =
                        watched?.remainingQty ??
                        field.remainingQty ??
                        (remainingFallback > 0 ? remainingFallback : 0);
                    const plannedQty =
                        watched?.qtyReceived ?? field.qtyReceived ?? 0;
                    const uom = watched?.uom ?? field.uom ?? 'units';
                    const isOverReceipt =
                        Number.isFinite(remaining) &&
                        plannedQty > (remaining ?? 0);

                    const handleFillRemaining = () => {
                        const target = remaining ?? 0;
                        setValue(
                            `lines.${index}.qtyReceived` as Path<FormValues>,
                            target as PathValue<FormValues, Path<FormValues>>,
                            {
                                shouldDirty: true,
                                shouldTouch: true,
                            },
                        );
                    };

                    return (
                        <Card
                            key={field.id ?? field.poLineId}
                            className="border-border/70"
                        >
                            <CardContent className="space-y-4 py-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">
                                            Line {field.lineNo ?? index + 1}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {field.description ??
                                                'Purchase order line'}
                                        </p>
                                    </div>
                                    <Badge variant="outline">{uom}</Badge>
                                </div>

                                <div className="grid gap-4 md:grid-cols-4">
                                    <div>
                                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                            Ordered
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {formatQty(ordered, formatNumber)}{' '}
                                            {uom}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                            Received
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {formatQty(
                                                previously,
                                                formatNumber,
                                            )}{' '}
                                            {uom}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                            Remaining
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {formatQty(
                                                remaining ?? 0,
                                                formatNumber,
                                            )}{' '}
                                            {uom}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`line-qty-${field.id ?? field.poLineId}`}
                                        >
                                            Quantity received
                                        </Label>
                                        <div className="flex gap-2">
                                            <Input
                                                id={`line-qty-${field.id ?? field.poLineId}`}
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                disabled={disabled}
                                                inputMode="decimal"
                                                {...register(
                                                    `lines.${index}.qtyReceived` as Path<FormValues>,
                                                    { valueAsNumber: true },
                                                )}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleFillRemaining}
                                                disabled={
                                                    disabled ||
                                                    !Number.isFinite(remaining)
                                                }
                                            >
                                                Fill remaining
                                            </Button>
                                        </div>
                                        {lineErrors?.qtyReceived ? (
                                            <p className="text-xs text-destructive">
                                                {lineErrors.qtyReceived.message}
                                            </p>
                                        ) : null}
                                        {!lineErrors?.qtyReceived ? (
                                            <p
                                                className={`text-xs ${isOverReceipt ? 'text-amber-600' : 'text-muted-foreground'}`}
                                            >
                                                {isOverReceipt ? (
                                                    <span className="flex items-center gap-1">
                                                        <AlertTriangle className="h-3.5 w-3.5" />
                                                        Over by{' '}
                                                        {formatQty(
                                                            plannedQty -
                                                                (remaining ??
                                                                    0),
                                                            formatNumber,
                                                        )}{' '}
                                                        {uom}
                                                    </span>
                                                ) : (
                                                    `Leave blank or enter 0 if nothing arrived.`
                                                )}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label
                                            htmlFor={`line-notes-${field.id ?? field.poLineId}`}
                                        >
                                            Line note
                                        </Label>
                                        <Textarea
                                            id={`line-notes-${field.id ?? field.poLineId}`}
                                            rows={3}
                                            placeholder="Damage, overage, or carrier notes"
                                            disabled={disabled}
                                            {...register(
                                                `lines.${index}.notes` as Path<FormValues>,
                                            )}
                                        />
                                        {lineErrors?.notes ? (
                                            <p className="text-xs text-destructive">
                                                {lineErrors.notes.message}
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );
}

function formatQty(
    value: number | null | undefined,
    formatter?: FormattingContextValue['formatNumber'],
): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '0';
    }

    const absValue = Math.abs(value);

    if (formatter) {
        const formatted = formatter(value, {
            minimumFractionDigits: absValue >= 1 ? 0 : 3,
            maximumFractionDigits: absValue >= 1 ? 2 : 3,
        });
        return absValue >= 1
            ? formatted
            : formatted.replace(/0+$/, '').replace(/\.$/, '') || '0';
    }

    if (absValue >= 1) {
        return value.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    return value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') || '0';
}
