import { useMemo } from 'react';
import { Controller, UseFormReturn, useWatch } from 'react-hook-form';
import { Info, ShieldAlert } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { RfqAwardCandidateLine, RfqAwardCandidateOption } from '@/sdk';
import type { AwardFormValues } from '@/pages/awards/award-form-schema';
import { cn } from '@/lib/utils';

interface AwardLinePickerProps {
    lines: RfqAwardCandidateLine[];
    form: UseFormReturn<AwardFormValues>;
    isSubmitting?: boolean;
    isLoading?: boolean;
    companyCurrency?: string;
}

function formatMinorCurrency(value?: number | null, currency?: string): string {
    if (value === undefined || value === null || Number.isNaN(value) || !currency) {
        return '—';
    }

    const major = value / 100;

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(major);
    } catch (error) {
        void error;
        return `${major.toFixed(2)} ${currency}`;
    }
}

function getDisplayPrice(candidate: RfqAwardCandidateOption, companyCurrency?: string) {
    if (candidate.convertedUnitPriceMinor != null && candidate.convertedCurrency) {
        return {
            label: formatMinorCurrency(candidate.convertedUnitPriceMinor, candidate.convertedCurrency),
            helper:
                candidate.unitPriceCurrency && candidate.unitPriceCurrency !== candidate.convertedCurrency
                    ? `${formatMinorCurrency(candidate.unitPriceMinor ?? 0, candidate.unitPriceCurrency)} (${candidate.unitPriceCurrency})`
                    : undefined,
        };
    }

    if (candidate.unitPriceMinor != null && candidate.unitPriceCurrency) {
        return {
            label: formatMinorCurrency(candidate.unitPriceMinor, candidate.unitPriceCurrency),
            helper:
                companyCurrency && companyCurrency !== candidate.unitPriceCurrency
                    ? `FX unavailable for ${companyCurrency}`
                    : undefined,
        };
    }

    return {
        label: '—',
        helper: undefined,
    };
}

function clampAwardQuantity(value: number, max?: number) {
    if (!Number.isFinite(value)) {
        return undefined;
    }
    const upperBound = typeof max === 'number' && max > 0 ? Math.floor(max) : undefined;
    const normalized = Math.max(1, Math.floor(value));
    if (upperBound === undefined) {
        return normalized;
    }
    return Math.min(normalized, upperBound);
}

export function AwardLinePicker({
    lines,
    form,
    isSubmitting = false,
    isLoading = false,
    companyCurrency,
}: AwardLinePickerProps) {
    const selections = useWatch({ control: form.control, name: 'lines' }) ?? [];

    const indexByLineId = useMemo(() => {
        const map = new Map<number, number>();
        lines.forEach((line, index) => {
            map.set(line.id, index);
        });
        return map;
    }, [lines]);

    const handleCandidateSelection = (lineId: number, candidate: RfqAwardCandidateOption) => {
        const index = indexByLineId.get(lineId);
        if (index === undefined) {
            return;
        }

        form.setValue(`lines.${index}.rfqItemId`, lineId, { shouldDirty: true });
        form.setValue(`lines.${index}.quoteItemId`, candidate.quoteItemId, { shouldDirty: true });

        const quantity = selections?.[index]?.awardedQty ?? lines[index]?.quantity ?? 1;
        if (!quantity || quantity <= 0) {
            form.setValue(`lines.${index}.awardedQty`, lines[index]?.quantity ?? 1, { shouldDirty: true });
        }
    };

    const handleClearSelection = (lineId: number) => {
        const index = indexByLineId.get(lineId);
        if (index === undefined) {
            return;
        }

        form.setValue(`lines.${index}.quoteItemId`, undefined, { shouldDirty: true });
    };

    if (isLoading) {
        return (
            <div className="space-y-4">
                {Array.from({ length: 3 }).map((_, idx) => (
                    <Skeleton key={idx} className="h-32 w-full" />
                ))}
            </div>
        );
    }

    if (lines.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-md border border-dashed p-8 text-center text-muted-foreground">
                <ShieldAlert className="mb-3 h-8 w-8 text-muted-foreground" />
                <p className="text-sm">No RFQ lines available for awarding yet.</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {lines.map((line, index) => {
                const selection = selections?.[index];
                const selectedCandidate = line.candidates.find((candidate) => candidate.quoteItemId === selection?.quoteItemId);

                return (
                    <Card key={line.id} className="border-border/70">
                        <CardHeader className="flex flex-col gap-2 space-y-0 border-b py-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle className="text-base font-semibold text-foreground">
                                    Line {line.lineNo}: {line.partName}
                                </CardTitle>
                                {line.bestPrice?.quoteItemId && selectedCandidate?.quoteItemId === line.bestPrice.quoteItemId ? (
                                    <Badge variant="secondary" className="text-xs">
                                        Best price
                                    </Badge>
                                ) : null}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Qty {line.quantity} {line.uom ?? ''}
                                {line.spec ? <span className="ml-2">• {line.spec}</span> : null}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 py-4">
                            <fieldset className="space-y-3">
                                <legend className="sr-only">Select supplier</legend>
                                {line.candidates.length === 0 ? (
                                    <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                                        No supplier responses captured for this line.
                                    </p>
                                ) : (
                                    line.candidates.map((candidate) => {
                                        const price = getDisplayPrice(candidate, companyCurrency);
                                        const isChecked = candidate.quoteItemId === selectedCandidate?.quoteItemId;

                                        return (
                                            <label
                                                key={candidate.quoteItemId}
                                                className={cn(
                                                    'flex flex-col gap-1 rounded-md border p-3 text-sm transition hover:border-primary/40',
                                                    isChecked ? 'border-primary shadow-sm' : 'border-border',
                                                )}
                                            >
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <input
                                                        type="radio"
                                                        name={`line-${line.id}`}
                                                        className="h-4 w-4"
                                                        checked={isChecked}
                                                        onChange={() => handleCandidateSelection(line.id, candidate)}
                                                        disabled={isSubmitting}
                                                    />
                                                    <span className="font-medium text-foreground">{candidate.supplierName ?? `Supplier #${candidate.supplierId}`}</span>
                                                    {candidate.quoteStatus ? (
                                                        <Badge variant="outline" className="text-xs capitalize">
                                                            {candidate.quoteStatus.replace(/_/g, ' ')}
                                                        </Badge>
                                                    ) : null}
                                                    {candidate.leadTimeDays ? (
                                                        <span className="text-xs text-muted-foreground">
                                                            Lead time: {candidate.leadTimeDays} days
                                                        </span>
                                                    ) : null}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2 pl-7 text-sm text-muted-foreground">
                                                    <span className="font-semibold text-foreground">{price.label}</span>
                                                    {price.helper ? (
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger className="inline-flex items-center text-muted-foreground">
                                                                    <Info className="mr-1 h-3.5 w-3.5" />
                                                                    <span className="text-xs">Details</span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p className="text-xs">{price.helper}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    ) : null}
                                                    {candidate.conversionUnavailable ? (
                                                        <Badge variant="secondary" className="text-[10px] uppercase">
                                                            FX unavailable
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            </label>
                                        );
                                    })
                                )}
                            </fieldset>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor={`award-qty-${line.id}`} className="text-xs uppercase text-muted-foreground">
                                    Award quantity
                                </Label>
                                <Controller
                                    control={form.control}
                                    name={`lines.${index}.awardedQty`}
                                    render={({ field }) => (
                                        <Input
                                            id={`award-qty-${line.id}`}
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={line.quantity}
                                            step={1}
                                            disabled={!selectedCandidate || isSubmitting}
                                            value={field.value ?? ''}
                                            onChange={(event) => {
                                                const rawValue = Number(event.currentTarget.value);
                                                if (Number.isNaN(rawValue)) {
                                                    field.onChange(undefined);
                                                    return;
                                                }
                                                const clamped = clampAwardQuantity(rawValue, line.quantity);
                                                field.onChange(clamped);
                                            }}
                                            onBlur={(event) => {
                                                field.onBlur();
                                                const rawValue = Number(event.currentTarget.value);
                                                if (Number.isNaN(rawValue)) {
                                                    field.onChange(line.quantity);
                                                    return;
                                                }
                                                const clamped = clampAwardQuantity(rawValue, line.quantity);
                                                if (clamped !== rawValue) {
                                                    field.onChange(clamped);
                                                }
                                            }}
                                        />
                                    )}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Default is RFQ quantity. Adjust if you only need a partial award.
                                </p>
                            </div>

                            {selectedCandidate ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-xs text-muted-foreground"
                                        onClick={() => handleClearSelection(line.id)}
                                        disabled={isSubmitting}
                                    >
                                        Clear selection
                                    </Button>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                );
            })}
        </div>
    );
}
