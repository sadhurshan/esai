import { useEffect } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';

const rfqLineSchema = z.object({
    partName: z.string().min(1, 'Part name is required.'),
    spec: z.string().optional(),
    quantity: z.coerce
        .number({ invalid_type_error: 'Quantity must be a number.' })
        .positive('Quantity must be greater than zero.'),
    uom: z.string().min(1, 'Unit of measure is required.'),
    targetPrice: z
        .union([z.coerce.number({ invalid_type_error: 'Target price must be a number.' }), z.literal('')])
        .optional()
        .transform((value) => (value === '' ? undefined : value)),
    requiredDate: z
        .string()
        .min(1, 'Required date is required.')
        .refine((value) => {
            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) {
                return false;
            }

            const startOfToday = new Date();
            startOfToday.setHours(0, 0, 0, 0);
            return parsed >= startOfToday;
        }, 'Required date cannot be in the past.'),
    notes: z.string().optional(),
});

export type RfqLineFormValues = z.infer<typeof rfqLineSchema>;

export interface RfqLineEditorModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (values: RfqLineFormValues) => Promise<void> | void;
    initialValues?: Partial<RfqLineFormValues>;
    title?: string;
    description?: string;
    submitLabel?: string;
    isSubmitting?: boolean;
}

export function RfqLineEditorModal({
    open,
    onOpenChange,
    onSubmit,
    initialValues,
    title = 'Add RFQ line',
    description = 'Capture the line item details that suppliers will quote against.',
    submitLabel = 'Save line',
    isSubmitting = false,
}: RfqLineEditorModalProps) {
    const form = useForm<RfqLineFormValues>({
        resolver: zodResolver(rfqLineSchema),
        defaultValues: {
            partName: '',
            spec: '',
            quantity: 1,
            uom: 'ea',
            targetPrice: undefined,
            requiredDate: '',
            notes: '',
            ...initialValues,
        },
    });

    useEffect(() => {
        if (initialValues) {
            const normalizedRequiredDate = initialValues.requiredDate
                ? initialValues.requiredDate.includes('T')
                    ? initialValues.requiredDate.slice(0, 10)
                    : initialValues.requiredDate
                : '';
            form.reset({
                partName: initialValues.partName ?? '',
                spec: initialValues.spec ?? '',
                quantity: initialValues.quantity ?? 1,
                uom: initialValues.uom ?? 'ea',
                targetPrice: initialValues.targetPrice,
                requiredDate: normalizedRequiredDate,
                notes: initialValues.notes ?? '',
            });
        }
    }, [initialValues, form]);

    const handleSubmit = form.handleSubmit(async (values) => {
        try {
            await onSubmit(values);
            onOpenChange(false);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to save RFQ line.';
            publishToast({
                variant: 'destructive',
                title: 'Line save failed',
                description: message,
            });
        }
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <form className="grid gap-4" onSubmit={handleSubmit}>
                    <div className="grid gap-2">
                        <Label htmlFor="partName">Part / Description</Label>
                        <Input id="partName" {...form.register('partName')} placeholder="Bracket, machined" />
                        {form.formState.errors.partName ? (
                            <p className="text-sm text-destructive">{form.formState.errors.partName.message}</p>
                        ) : null}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="spec">Specifications</Label>
                        <Textarea
                            id="spec"
                            {...form.register('spec')}
                            placeholder="Outline key tolerances, finish requirements, or inspection needs."
                            rows={3}
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="quantity">Quantity</Label>
                            <Input id="quantity" type="number" min="0" step="1" {...form.register('quantity')} />
                            {form.formState.errors.quantity ? (
                                <p className="text-sm text-destructive">{form.formState.errors.quantity.message}</p>
                            ) : null}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="uom">Unit of measure</Label>
                            <Input id="uom" {...form.register('uom')} placeholder="ea" />
                            {/* TODO: integrate unit conversion helper once localization endpoint is wired. */}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="targetPrice">Target price (optional)</Label>
                            <Input id="targetPrice" type="number" step="0.01" {...form.register('targetPrice')} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="requiredDate">Required date (optional)</Label>
                            <Input id="requiredDate" type="date" {...form.register('requiredDate')} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            {...form.register('notes')}
                            placeholder="Provide commercial notes or inspection callouts."
                            rows={2}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={isSubmitting}>
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
