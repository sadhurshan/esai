import { Input } from '@/components/ui/input';
import { FormField, FormItem, FormLabel, FormControl, FormMessage, FormDescription } from '@/components/ui/form';
import type { CompanyAddress } from '@/types/settings';
import type { Control, FieldPath, FieldValues } from 'react-hook-form';

interface AddressEditorProps<TFieldValues extends FieldValues> {
    control: Control<TFieldValues>;
    name: FieldPath<TFieldValues>;
    title: string;
    description?: string;
    disabled?: boolean;
}

function composeName<TFieldValues extends FieldValues>(name: FieldPath<TFieldValues>, key: keyof CompanyAddress) {
    return `${name}.${key}` as FieldPath<TFieldValues>;
}

export function AddressEditor<TFieldValues extends FieldValues>({
    control,
    name,
    title,
    description,
    disabled,
}: AddressEditorProps<TFieldValues>) {
    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">{title}</p>
                {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <FormField
                    control={control}
                    name={composeName(name, 'attention')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Attention</FormLabel>
                            <FormControl>
                                <Input placeholder="Accounts payable" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'line1')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Address line 1</FormLabel>
                            <FormControl>
                                <Input placeholder="1234 Market St" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'line2')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Address line 2</FormLabel>
                            <FormControl>
                                <Input placeholder="Suite 500" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'city')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>City</FormLabel>
                            <FormControl>
                                <Input placeholder="San Francisco" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'state')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>State / Region</FormLabel>
                            <FormControl>
                                <Input placeholder="CA" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'postalCode')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Postal code</FormLabel>
                            <FormDescription>Use company formatting conventions.</FormDescription>
                            <FormControl>
                                <Input placeholder="94103" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
                <FormField
                    control={control}
                    name={composeName(name, 'country')}
                    render={({ field }) => (
                        <FormItem>
                            <FormLabel>Country</FormLabel>
                            <FormControl>
                                <Input placeholder="US" {...field} disabled={disabled} />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    )}
                />
            </div>
        </div>
    );
}
