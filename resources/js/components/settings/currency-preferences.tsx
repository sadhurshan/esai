import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Control, FieldPath, FieldValues } from 'react-hook-form';

export interface CurrencyOption {
    value: string;
    label: string;
}

interface CurrencyPreferencesProps<TFieldValues extends FieldValues> {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    options: CurrencyOption[];
    disabled?: boolean;
}

function composeName<TFieldValues extends FieldValues>(name: FieldPath<TFieldValues>, key: 'primary' | 'displayFx') {
    return `${name}.${key}` as FieldPath<TFieldValues>;
}

export function CurrencyPreferences<TFieldValues extends FieldValues>({
    control,
    name,
    options,
    disabled,
}: CurrencyPreferencesProps<TFieldValues>) {
    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">Currency</p>
                <p className="text-sm text-muted-foreground">
                    Primary currency sets formatting defaults. Enable FX tooltips to show alternate conversions.
                </p>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <FormField
                    control={control}
                    name={composeName(name, 'primary')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Primary currency</FormLabel>
                            <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectGroup>
                                        {options.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'displayFx')}
                    render={({ field }) => (
                        <FormItem className="flex flex-col space-y-3">
                            <div className="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <FormLabel>Display FX tooltip</FormLabel>
                                    <FormDescription>
                                        Show hover tooltips with converted amounts using the tenant FX rate snapshot.
                                    </FormDescription>
                                </div>
                                <FormControl>
                                    <Checkbox
                                        checked={Boolean(field.value)}
                                        onCheckedChange={(checked) => field.onChange(Boolean(checked))}
                                        disabled={disabled}
                                    />
                                </FormControl>
                            </div>
                            <FormMessage />
                        </FormItem>
                    )}
                />
            </div>
        </div>
    );
}
