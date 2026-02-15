import { AlertTriangle, Plus, Trash2 } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    useFormatting,
    type FormattingContextValue,
} from '@/contexts/formatting-context';
import type { MovementType } from '@/sdk';
import type {
    InventoryItemSummary,
    InventoryLocationOption,
} from '@/types/inventory';
import { LocationSelect } from './location-select';

export interface MovementLineFormValue {
    id?: string;
    itemId?: string;
    qty?: number;
    uom?: string | null;
    fromLocationId?: string | null;
    toLocationId?: string | null;
    reason?: string | null;
}

export interface MovementFormLike extends FieldValues {
    lines: MovementLineFormValue[];
}

export interface MovementItemOption {
    id: string;
    label: string;
    sku: string;
    defaultUom?: string;
}

interface MovementLineEditorProps<FormValues extends MovementFormLike> {
    form: UseFormReturn<FormValues>;
    type: MovementType;
    itemOptions: MovementItemOption[];
    locations: InventoryLocationOption[];
    disabled?: boolean;
    itemSummaries?: Record<
        string,
        Pick<InventoryItemSummary, 'onHand' | 'defaultUom'>
    >;
    defaultDestinationId?: string | null;
}

export function MovementLineEditor<FormValues extends MovementFormLike>({
    form,
    type,
    itemOptions,
    locations,
    disabled,
    itemSummaries,
    defaultDestinationId,
}: MovementLineEditorProps<FormValues>) {
    const { formatNumber } = useFormatting();
    const { control, register, setValue, formState } = form;
    const linesPath = 'lines' as ArrayPath<FormValues>;
    const { fields, append, remove } = useFieldArray<
        FormValues,
        typeof linesPath,
        'id'
    >({ control, name: linesPath });
    const watchedLines = useWatch({
        control,
        name: linesPath as Path<FormValues>,
    }) as MovementLineFormValue[] | undefined;
    const locationsIndex = useMemo(
        () => new Map(locations.map((location) => [location.id, location])),
        [locations],
    );

    const isTransfer = type === 'TRANSFER';
    const isAdjust = type === 'ADJUST';
    const requiresIssueSource = type === 'ISSUE' || isTransfer;
    const requiresReceiptDestination = type === 'RECEIPT' || isTransfer;
    const showReason = isAdjust;

    const summary = useMemo(() => {
        const source = (watchedLines ?? fields) as MovementLineFormValue[];
        return source.reduce(
            (acc, line) => {
                const qty = Number(line?.qty ?? 0);
                if (Number.isFinite(qty)) {
                    acc.totalQty += qty;
                }
                acc.count += 1;
                return acc;
            },
            { totalQty: 0, count: 0 },
        );
    }, [fields, watchedLines]);

    const handleAddLine = () => {
        append({
            itemId: undefined,
            qty: undefined,
            uom: undefined,
            fromLocationId: undefined,
            toLocationId:
                requiresReceiptDestination && defaultDestinationId
                    ? defaultDestinationId
                    : undefined,
            reason: undefined,
        } as PathValue<FormValues, typeof linesPath>);
    };

    const handleItemChange = (index: number, itemId: string) => {
        const option = itemOptions.find((item) => item.id === itemId);
        setValue(
            `lines.${index}.itemId` as Path<FormValues>,
            itemId as PathValue<FormValues, Path<FormValues>>,
            {
                shouldDirty: true,
                shouldTouch: true,
            },
        );
        if (option?.defaultUom) {
            setValue(
                `lines.${index}.uom` as Path<FormValues>,
                option.defaultUom as PathValue<FormValues, Path<FormValues>>,
                {
                    shouldDirty: true,
                    shouldTouch: true,
                },
            );
        }
    };

    if (fields.length === 0) {
        return (
            <Alert>
                <AlertTitle>No lines added</AlertTitle>
                <AlertDescription className="flex flex-col gap-3">
                    Start by adding an item movement line.
                    <Button
                        type="button"
                        size="sm"
                        onClick={handleAddLine}
                        disabled={disabled}
                    >
                        <Plus className="mr-2 h-4 w-4" /> Add line
                    </Button>
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-4 rounded-lg border border-border/70 bg-muted/30 px-4 py-3 text-xs tracking-wide text-muted-foreground uppercase">
                <span>
                    Lines{' '}
                    <span className="font-semibold text-foreground">
                        {formatNumber(summary.count, {
                            maximumFractionDigits: 0,
                        })}
                    </span>
                </span>
                <span>
                    Total quantity{' '}
                    <span className="font-semibold text-foreground">
                        {formatQty(summary.totalQty, formatNumber)}
                    </span>
                </span>
            </div>

            <div className="space-y-4">
                {fields.map((field, index) => {
                    const lineErrors = Array.isArray(formState.errors.lines)
                        ? (formState.errors.lines[index] as
                              | Record<string, { message?: string }>
                              | undefined)
                        : undefined;
                    const watched =
                        watchedLines?.[index] ??
                        (field as MovementLineFormValue) ??
                        {};
                    const sameLocation = Boolean(
                        watched.fromLocationId &&
                        watched.toLocationId &&
                        watched.fromLocationId === watched.toLocationId,
                    );
                    const itemSummary = watched.itemId
                        ? itemSummaries?.[watched.itemId]
                        : undefined;
                    const itemOnHand =
                        typeof itemSummary?.onHand === 'number'
                            ? itemSummary.onHand
                            : null;
                    const qtyValue =
                        typeof watched.qty === 'number'
                            ? watched.qty
                            : Number(watched.qty ?? 0);
                    const locationMeta = watched.fromLocationId
                        ? locationsIndex.get(watched.fromLocationId)
                        : undefined;
                    const locationSupportsNegative =
                        locationMeta?.supportsNegative ?? false;
                    const qtyExceedsItem =
                        requiresIssueSource &&
                        itemOnHand !== null &&
                        Number.isFinite(qtyValue) &&
                        qtyValue > itemOnHand;

                    return (
                        <Card
                            key={field.id ?? index}
                            className="border-border/70"
                        >
                            <CardContent className="space-y-4 py-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">
                                            Line {index + 1}
                                        </p>
                                        {watched.itemId ? (
                                            <p className="text-xs text-muted-foreground">{`Item #${watched.itemId}`}</p>
                                        ) : null}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            className="text-xs"
                                        >
                                            {watched.uom ?? 'UoM'}
                                        </Badge>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            disabled={disabled}
                                            onClick={() => remove(index)}
                                            aria-label={`Remove line ${index + 1}`}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Inventory item</Label>
                                        <Select
                                            value={watched.itemId ?? undefined}
                                            onValueChange={(selection) =>
                                                handleItemChange(
                                                    index,
                                                    selection,
                                                )
                                            }
                                            disabled={disabled}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select item" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {itemOptions.length === 0 ? (
                                                    <div className="px-3 py-2 text-sm text-muted-foreground">
                                                        No inventory items
                                                        available.
                                                    </div>
                                                ) : (
                                                    itemOptions.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                <div className="flex flex-col">
                                                                    <span className="text-sm font-medium">
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </span>
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {
                                                                            option.sku
                                                                        }
                                                                    </span>
                                                                </div>
                                                            </SelectItem>
                                                        ),
                                                    )
                                                )}
                                            </SelectContent>
                                        </Select>
                                        {lineErrors?.itemId?.message ? (
                                            <p className="text-xs text-destructive">
                                                {lineErrors.itemId.message}
                                            </p>
                                        ) : null}
                                        {itemOnHand !== null ? (
                                            <p className="text-xs text-muted-foreground">
                                                On-hand:{' '}
                                                {formatQty(
                                                    itemOnHand,
                                                    formatNumber,
                                                    3,
                                                )}
                                                {itemSummary?.defaultUom
                                                    ? ` ${itemSummary.defaultUom}`
                                                    : ''}
                                            </p>
                                        ) : null}
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-2">
                                            <Label>Quantity</Label>
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                disabled={disabled}
                                                {...register(
                                                    `lines.${index}.qty` as Path<FormValues>,
                                                    { valueAsNumber: true },
                                                )}
                                            />
                                            {lineErrors?.qty?.message ? (
                                                <p className="text-xs text-destructive">
                                                    {lineErrors.qty.message}
                                                </p>
                                            ) : (
                                                <p className="text-xs text-muted-foreground">
                                                    Positive values only.
                                                </p>
                                            )}
                                            {qtyExceedsItem ? (
                                                <p className="text-xs text-destructive">
                                                    Requested quantity exceeds
                                                    available on-hand. Adjust
                                                    the quantity or pick another
                                                    SKU.
                                                </p>
                                            ) : null}
                                        </div>
                                        <div className="space-y-2">
                                            <Label>UoM</Label>
                                            <Input
                                                type="text"
                                                maxLength={8}
                                                disabled={disabled}
                                                {...register(
                                                    `lines.${index}.uom` as Path<FormValues>,
                                                )}
                                            />
                                            {lineErrors?.uom?.message ? (
                                                <p className="text-xs text-destructive">
                                                    {lineErrors.uom.message}
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    {requiresIssueSource || isAdjust ? (
                                        <div className="space-y-2">
                                            <Label>
                                                {isAdjust
                                                    ? 'Decrease location'
                                                    : 'From location'}
                                            </Label>
                                            <LocationSelect
                                                options={locations}
                                                value={
                                                    watched.fromLocationId ??
                                                    null
                                                }
                                                onChange={(next) =>
                                                    setValue(
                                                        `lines.${index}.fromLocationId` as Path<FormValues>,
                                                        (next ??
                                                            undefined) as PathValue<
                                                            FormValues,
                                                            Path<FormValues>
                                                        >,
                                                        {
                                                            shouldDirty: true,
                                                            shouldTouch: true,
                                                        },
                                                    )
                                                }
                                                disabled={disabled}
                                                placeholder="Select source"
                                            />
                                            {isAdjust ? (
                                                <p className="text-xs text-muted-foreground">
                                                    Optional. Set when removing
                                                    stock from a specific bin or
                                                    warehouse.
                                                </p>
                                            ) : null}
                                            {lineErrors?.fromLocationId
                                                ?.message ? (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        lineErrors
                                                            .fromLocationId
                                                            .message
                                                    }
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <div />
                                    )}
                                    {requiresReceiptDestination || isAdjust ? (
                                        <div className="space-y-2">
                                            <Label>
                                                {isAdjust
                                                    ? 'Increase location'
                                                    : 'To location'}
                                            </Label>
                                            <LocationSelect
                                                options={locations}
                                                value={
                                                    watched.toLocationId ?? null
                                                }
                                                onChange={(next) =>
                                                    setValue(
                                                        `lines.${index}.toLocationId` as Path<FormValues>,
                                                        (next ??
                                                            undefined) as PathValue<
                                                            FormValues,
                                                            Path<FormValues>
                                                        >,
                                                        {
                                                            shouldDirty: true,
                                                            shouldTouch: true,
                                                        },
                                                    )
                                                }
                                                disabled={disabled}
                                                placeholder="Select destination"
                                            />
                                            {isAdjust ? (
                                                <p className="text-xs text-muted-foreground">
                                                    Optional. Set when adding
                                                    stock into a location after
                                                    the adjustment.
                                                </p>
                                            ) : null}
                                            {lineErrors?.toLocationId
                                                ?.message ? (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        lineErrors.toLocationId
                                                            .message
                                                    }
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : (
                                        <div />
                                    )}
                                </div>

                                {showReason ? (
                                    <div className="space-y-2">
                                        <Label>Adjustment reason</Label>
                                        <Textarea
                                            rows={3}
                                            disabled={disabled}
                                            placeholder="Breakage, cycle count variance, etc."
                                            {...register(
                                                `lines.${index}.reason` as Path<FormValues>,
                                            )}
                                        />
                                        {lineErrors?.reason?.message ? (
                                            <p className="text-xs text-destructive">
                                                {lineErrors.reason.message}
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}

                                {isTransfer && sameLocation ? (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertTitle>
                                            Same source and destination
                                        </AlertTitle>
                                        <AlertDescription>
                                            Transfers require distinct
                                            locations. Update one of the
                                            selections.
                                        </AlertDescription>
                                    </Alert>
                                ) : null}
                                {qtyExceedsItem && locationSupportsNegative ? (
                                    <Alert>
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertTitle>
                                            Negative stock override
                                        </AlertTitle>
                                        <AlertDescription>
                                            This location allows negative
                                            balances. Double-check the quantity
                                            before posting.
                                        </AlertDescription>
                                    </Alert>
                                ) : null}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            <div className="flex justify-start">
                <Button
                    type="button"
                    variant="outline"
                    onClick={handleAddLine}
                    disabled={disabled}
                >
                    <Plus className="mr-2 h-4 w-4" /> Add line
                </Button>
            </div>
        </div>
    );
}

function formatQty(
    value: number | null | undefined,
    formatter: FormattingContextValue['formatNumber'],
    forcedPrecision?: number,
) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return '0';
    }

    const precision = forcedPrecision ?? (Math.abs(value) >= 1 ? 2 : 3);
    return formatter(value, {
        maximumFractionDigits: precision,
        fallback: '0',
    });
}
