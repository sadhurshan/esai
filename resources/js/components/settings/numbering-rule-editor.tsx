import { useEffect, useMemo, useRef, useState } from 'react';
import { useWatch, type Control, type FieldPath, type FieldValues } from 'react-hook-form';

import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatNumberingSample } from '@/lib/numbering';
import type { NumberingRule, NumberResetPolicy } from '@/types/settings';

interface NumberingRuleEditorProps<TFieldValues extends FieldValues> {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    label: string;
    description?: string;
    disabled?: boolean;
}

function composeName<TFieldValues extends FieldValues>(name: FieldPath<TFieldValues>, key: keyof NumberingRule) {
    return `${name}.${key}` as FieldPath<TFieldValues>;
}

const resetOptions: { label: string; value: NumberResetPolicy; description: string }[] = [
    { label: 'Never', value: 'never', description: 'Sequences continue incrementing indefinitely.' },
    { label: 'Yearly', value: 'yearly', description: 'Reset the counter at the start of each fiscal year.' },
];

export function NumberingRuleEditor<TFieldValues extends FieldValues>({
    control,
    name,
    label,
    description,
    disabled,
}: NumberingRuleEditorProps<TFieldValues>) {
    const watchedRule = useWatch({ control, name }) as NumberingRule | undefined;
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [confirmCopy, setConfirmCopy] = useState({ title: '', description: '', confirmLabel: 'Continue' });
    const pendingAction = useRef<(() => void) | null>(null);
    const lastSequenceLength = useRef<number | null>(null);
    const lastReset = useRef<NumberResetPolicy | null>(null);

    useEffect(() => {
        lastSequenceLength.current = watchedRule?.sequenceLength ?? null;
    }, [watchedRule?.sequenceLength]);

    useEffect(() => {
        lastReset.current = watchedRule?.reset ?? null;
    }, [watchedRule?.reset]);

    const sample = useMemo(() => formatNumberingSample(watchedRule), [watchedRule]);

    const requestConfirm = (type: 'sequence' | 'reset', action: () => void) => {
        pendingAction.current = action;
        if (type === 'sequence') {
            setConfirmCopy({
                title: 'Shorten sequence padding?',
                description:
                    'Reducing the sequence length can cause duplicate document numbers if existing values exceed the new padding. Continue only if downstream systems are aware of the change.',
                confirmLabel: 'Shorten padding',
            });
        } else {
            setConfirmCopy({
                title: 'Change reset cadence?',
                description:
                    'Altering the reset cadence impacts how next numbers roll over. Confirm that accounting and integrations are ready for the change.',
                confirmLabel: 'Change cadence',
            });
        }
        setConfirmOpen(true);
    };

    const handleConfirm = () => {
        pendingAction.current?.();
        pendingAction.current = null;
        setConfirmOpen(false);
    };

    const handleCancel = () => {
        pendingAction.current = null;
        setConfirmOpen(false);
    };

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">{label}</p>
                {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
                <p className="text-xs text-muted-foreground">Sample: {sample}</p>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <FormField
                    control={control}
                    name={composeName(name, 'prefix')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Prefix</FormLabel>
                            <FormControl>
                                <Input placeholder="PO-" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'sequenceLength')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Sequence length</FormLabel>
                            <FormControl>
                                <Input
                                    type="number"
                                    min={3}
                                    max={10}
                                    value={field.value ?? ''}
                                    disabled={disabled}
                                    onChange={(event) => {
                                        const raw = event.target.value;
                                        if (raw === '') {
                                            field.onChange(undefined);
                                            return;
                                        }
                                        const value = Number(raw);
                                        if (Number.isNaN(value)) {
                                            return;
                                        }
                                        if (
                                            lastSequenceLength.current !== null &&
                                            value < lastSequenceLength.current
                                        ) {
                        requestConfirm('sequence', () => field.onChange(value));
                                            return;
                                        }
                                        field.onChange(value);
                                    }}
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'next')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Next number</FormLabel>
                            <FormControl>
                                <Input
                                    type="number"
                                    min={1}
                                    value={field.value ?? ''}
                                    disabled={disabled}
                                    onChange={(event) => {
                                        const raw = event.target.value;
                                        if (raw === '') {
                                            field.onChange(undefined);
                                            return;
                                        }
                                        const value = Number(raw);
                                        if (Number.isNaN(value)) {
                                            return;
                                        }
                                        field.onChange(value);
                                    }}
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'reset')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Reset cadence</FormLabel>
                            <Select
                                value={(field.value as NumberResetPolicy | undefined) ?? ''}
                                onValueChange={(value: NumberResetPolicy) => {
                                    if (lastReset.current && value !== lastReset.current) {
                                        requestConfirm('reset', () => field.onChange(value));
                                        return;
                                    }
                                    field.onChange(value);
                                }}
                                disabled={disabled}
                            >
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    {resetOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {resetOptions.find((option) => option.value === field.value)?.description}
                            </p>
                            <FormMessage />
                        </FormItem>
                    )}
                />
            </div>
            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={(open) => (open ? setConfirmOpen(true) : handleCancel())}
                title={confirmCopy.title}
                description={confirmCopy.description}
                confirmLabel={confirmCopy.confirmLabel}
                onConfirm={handleConfirm}
            />
        </div>
    );
}
