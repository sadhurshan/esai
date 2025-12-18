import { useEffect, useMemo, useRef } from 'react';
import { z } from 'zod';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { SalesOrderLine } from '@/types/orders';

const shipmentLineSchema = z.object({
    soLineId: z.number(),
    qtyShipped: z.number().nonnegative(),
    maxQty: z.number().nonnegative(),
});

const shipmentSchema = z
    .object({
        carrier: z.string().trim().min(1, { message: 'Carrier is required' }),
        trackingNumber: z.string().trim().min(1, { message: 'Tracking number is required' }),
        shippedAt: z.string().trim().min(1, { message: 'Ship date is required' }),
        notes: z.string().optional(),
        lines: z.array(shipmentLineSchema),
    })
    .superRefine((values, ctx) => {
        if (!values.lines.some((line) => line.qtyShipped > 0)) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: 'Enter a shipped quantity for at least one line.',
                path: ['lines'],
            });
        }

        values.lines.forEach((line, index) => {
            if (line.qtyShipped > line.maxQty) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: `Only ${line.maxQty} units remaining.`,
                    path: ['lines', index, 'qtyShipped'],
                });
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message: `Line #${line.soLineId} only has ${line.maxQty} units remaining.`,
                    path: ['lines'],
                });
            }
        });
    });

export type ShipmentFormValues = z.infer<typeof shipmentSchema>;

export interface ShipmentCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    lines: SalesOrderLine[];
    onSubmit: (payload: { carrier: string; trackingNumber: string; shippedAt: string; notes?: string | null; lines: { soLineId: number; qtyShipped: number }[] }) => Promise<void> | void;
    isSubmitting?: boolean;
    defaultCarrier?: string;
    defaultTrackingNumber?: string;
}

export function ShipmentCreateDialog({
    open,
    onOpenChange,
    lines,
    onSubmit,
    isSubmitting,
    defaultCarrier,
    defaultTrackingNumber,
}: ShipmentCreateDialogProps) {
    const fulfillableLines = useMemo(() => {
        return lines
            .map((line) => {
                const remaining = Math.max((line.qtyOrdered ?? 0) - (line.qtyShipped ?? 0), 0);
                return {
                    soLineId: line.soLineId ?? line.id,
                    description: line.description,
                    sku: line.sku,
                    remaining,
                    uom: line.uom,
                };
            })
            .filter((line) => line.remaining > 0);
    }, [lines]);

    const form = useForm<ShipmentFormValues>({
        resolver: zodResolver(shipmentSchema),
        defaultValues: {
            carrier: defaultCarrier ?? '',
            trackingNumber: defaultTrackingNumber ?? '',
            shippedAt: new Date().toISOString().slice(0, 16),
            notes: '',
            lines: fulfillableLines.map((line) => ({
                soLineId: line.soLineId,
                qtyShipped: 0,
                maxQty: line.remaining,
            })),
        },
    });

    const wasOpenRef = useRef<boolean>(open);

    useEffect(() => {
        const justOpened = open && !wasOpenRef.current;
        wasOpenRef.current = open;

        if (!justOpened) {
            return;
        }

        form.reset({
            carrier: defaultCarrier ?? '',
            trackingNumber: defaultTrackingNumber ?? '',
            shippedAt: new Date().toISOString().slice(0, 16),
            notes: '',
            lines: fulfillableLines.map((line) => ({
                soLineId: line.soLineId,
                qtyShipped: 0,
                maxQty: line.remaining,
            })),
        });
    }, [open, fulfillableLines, defaultCarrier, defaultTrackingNumber, form]);

    const handleSubmit = async (values: ShipmentFormValues) => {
        const payload = {
            carrier: values.carrier.trim(),
            trackingNumber: values.trackingNumber.trim(),
            shippedAt: values.shippedAt,
            notes: values.notes?.trim() ? values.notes.trim() : undefined,
            lines: values.lines
                .filter((line) => line.qtyShipped > 0)
                .map((line) => ({ soLineId: line.soLineId, qtyShipped: line.qtyShipped })),
        };

        await onSubmit(payload);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="!max-w-5xl">
                <DialogHeader>
                    <DialogTitle>Create shipment</DialogTitle>
                    <DialogDescription>Select the lines and quantities you are ready to ship.</DialogDescription>
                </DialogHeader>
                {fulfillableLines.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-muted-foreground/50 p-6 text-sm text-muted-foreground">
                        All lines are fully shipped. Create shipments once new quantities are ready.
                    </div>
                ) : (
                    <Form {...form}>
                        <form onSubmit={form.handleSubmit(handleSubmit)} noValidate className="space-y-6">
                            <div className="grid gap-4 md:grid-cols-2">
                                <FormField
                                    control={form.control}
                                    name="carrier"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Carrier</FormLabel>
                                            <FormControl>
                                                <Input placeholder="Enter carrier name" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                                <FormField
                                    control={form.control}
                                    name="trackingNumber"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Tracking number</FormLabel>
                                            <FormControl>
                                                <Input placeholder="e.g. 1Z9999" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                                <FormField
                                    control={form.control}
                                    name="shippedAt"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Ship date</FormLabel>
                                            <FormControl>
                                                <Input type="datetime-local" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                                <FormField
                                    control={form.control}
                                    name="notes"
                                    render={({ field }) => (
                                        <FormItem className="md:col-span-2">
                                            <FormLabel>Notes</FormLabel>
                                            <FormControl>
                                                <Textarea rows={3} placeholder="Optional special instructions" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            </div>

                            <div className="rounded-lg border border-border/60">
                                <div className="border-b border-border/60 px-4 py-3">
                                    <p className="text-sm font-semibold text-foreground">Line allocations</p>
                                    <p className="text-xs text-muted-foreground">
                                        Remaining quantities are based on ordered minus previously shipped amounts.
                                    </p>
                                </div>
                                <div className="max-h-72 overflow-auto">
                                    <table className="w-full min-w-[720px] table-fixed text-sm">
                                        <thead className="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-2 text-left">Line</th>
                                                <th className="px-4 py-2 text-left">Description</th>
                                                <th className="px-4 py-2 text-left">Remaining</th>
                                                <th className="px-4 py-2 text-left">Ship qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {fulfillableLines.map((line, index) => (
                                                <tr key={line.soLineId} className="border-b border-border/40 last:border-b-0">
                                                    <td className="px-4 py-2 font-semibold text-muted-foreground">#{line.soLineId}</td>
                                                    <td className="px-4 py-2">
                                                        <p className="font-medium text-foreground">{line.description ?? '—'}</p>
                                                        {line.sku ? (
                                                            <span className="text-xs text-muted-foreground">SKU: {line.sku}</span>
                                                        ) : null}
                                                    </td>
                                                    <td className="px-4 py-2 text-sm text-muted-foreground">
                                                        {line.remaining} {line.uom ?? ''}
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        <FormField
                                                            control={form.control}
                                                            name={`lines.${index}.qtyShipped`}
                                                            render={({ field }) => {
                                                                const fieldState = form.getFieldState(
                                                                    `lines.${index}.qtyShipped` as const,
                                                                    form.formState,
                                                                );
                                                                const numericValue = Number(field.value ?? 0);
                                                                const overLimit = numericValue > line.remaining;
                                                                const qtyErrorMessage = overLimit
                                                                    ? `Line #${line.soLineId} only has ${line.remaining} units remaining.`
                                                                    : fieldState.error?.message;
                                                                const errorId = `line-${line.soLineId}-qty-error`;

                                                                return (
                                                                    <FormItem>
                                                                        <FormControl>
                                                                            <Input
                                                                                type="number"
                                                                                min={0}
                                                                                max={line.remaining}
                                                                                step="1"
                                                                                aria-label={`Ship quantity for line #${line.soLineId}`}
                                                                                aria-describedby={qtyErrorMessage ? errorId : undefined}
                                                                                value={Number.isFinite(field.value) ? field.value : ''}
                                                                                onChange={(event) => {
                                                                                    const parsed = Number(event.target.value);
                                                                                    field.onChange(Number.isNaN(parsed) ? 0 : parsed);
                                                                                }}
                                                                            />
                                                                        </FormControl>
                                                                        {qtyErrorMessage ? (
                                                                            <p id={errorId} className="text-sm font-medium text-destructive">
                                                                                {qtyErrorMessage}
                                                                            </p>
                                                                        ) : null}
                                                                    </FormItem>
                                                                );
                                                            }}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                {(() => {
                                    const lineError = form.formState.errors.lines;
                                    const message =
                                        typeof lineError === 'string'
                                            ? lineError
                                            : Array.isArray(lineError)
                                                ? lineError
                                                      .flatMap((error) => (typeof error === 'string' ? error : error?._errors ?? []))
                                                      .join(' ')
                                                : lineError?.message;
                                    if (!message) {
                                        return null;
                                    }
                                    return (
                                        <p className="px-4 py-2 text-sm text-destructive">
                                            {message}
                                        </p>
                                    );
                                })()}
                            </div>

                            <DialogFooter className="gap-2">
                                <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={isSubmitting}>
                                    {isSubmitting ? 'Creating…' : 'Create shipment'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
