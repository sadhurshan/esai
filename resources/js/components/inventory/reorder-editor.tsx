import { Info } from 'lucide-react';
import type { ChangeEvent } from 'react';

import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface ReorderEditorValue {
    minStock?: number | null;
    reorderQty?: number | null;
    leadTimeDays?: number | null;
}

interface ReorderEditorProps {
    value: ReorderEditorValue;
    onChange: (value: ReorderEditorValue) => void;
    disabled?: boolean;
    errors?: Partial<Record<keyof ReorderEditorValue, string>>;
    className?: string;
}

export function ReorderEditor({
    value,
    onChange,
    disabled,
    errors,
    className,
}: ReorderEditorProps) {
    const handleChange =
        (field: keyof ReorderEditorValue) =>
        (event: ChangeEvent<HTMLInputElement>) => {
            const nextValue = event.target.value;
            const numericValue = Number(nextValue);
            onChange({
                ...value,
                [field]:
                    nextValue === ''
                        ? null
                        : Number.isFinite(numericValue)
                          ? numericValue
                          : (value[field] ?? null),
            });
        };

    return (
        <Card className={cn('border-border/70', className)}>
            <CardContent className="space-y-6 py-6">
                <div className="flex items-start gap-3 text-sm text-muted-foreground">
                    <Info className="h-4 w-4 shrink-0 text-primary" />
                    <p>
                        Define minimum stock, reorder quantity, and lead time to
                        unlock low-stock alerts and purchasing recommendations.
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                        <Label htmlFor="min-stock">Minimum on-hand</Label>
                        <Input
                            id="min-stock"
                            type="number"
                            min={0}
                            step={1}
                            inputMode="numeric"
                            value={value.minStock ?? ''}
                            onChange={handleChange('minStock')}
                            disabled={disabled}
                        />
                        {errors?.minStock ? (
                            <p className="text-xs text-destructive">
                                {errors.minStock}
                            </p>
                        ) : null}
                        <p className="text-xs text-muted-foreground">
                            Alerts trigger when on-hand falls below this value.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="reorder-qty">Reorder quantity</Label>
                        <Input
                            id="reorder-qty"
                            type="number"
                            min={0}
                            step={1}
                            inputMode="numeric"
                            value={value.reorderQty ?? ''}
                            onChange={handleChange('reorderQty')}
                            disabled={disabled}
                        />
                        {errors?.reorderQty ? (
                            <p className="text-xs text-destructive">
                                {errors.reorderQty}
                            </p>
                        ) : null}
                        <p className="text-xs text-muted-foreground">
                            Suggested quantity when raising RFQs or POs.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="lead-time">Lead time (days)</Label>
                        <Input
                            id="lead-time"
                            type="number"
                            min={0}
                            step={1}
                            inputMode="numeric"
                            value={value.leadTimeDays ?? ''}
                            onChange={handleChange('leadTimeDays')}
                            disabled={disabled}
                        />
                        {errors?.leadTimeDays ? (
                            <p className="text-xs text-destructive">
                                {errors.leadTimeDays}
                            </p>
                        ) : null}
                        <p className="text-xs text-muted-foreground">
                            Used to suggest reorder dates when stock is low.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
