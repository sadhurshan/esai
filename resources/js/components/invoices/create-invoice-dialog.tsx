import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useMemo } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';

import { DocumentNumberPreview } from '@/components/documents/document-number-preview';
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
import {
    useFormatting,
    type FormattingContextValue,
} from '@/contexts/formatting-context';
import type { CreateInvoiceInput } from '@/hooks/api/invoices/use-create-invoice';
import type { PurchaseOrderDetail, PurchaseOrderLine } from '@/types/sourcing';

const createInvoiceLineSchema = z.object({
    poLineId: z.number().int().positive(),
    lineNo: z.number().int().nonnegative(),
    description: z.string().optional(),
    remainingQuantity: z.number().nonnegative(),
    qtyInvoiced: z.coerce.number().min(0, 'Quantity must be zero or greater.'),
    unitPrice: z.coerce.number().min(0, 'Unit price must be zero or greater.'),
    poUnitPrice: z.number().nonnegative().optional(),
});

const createInvoiceFormSchema = z
    .object({
        invoiceNumber: z.string().trim().min(1, 'Invoice number is required.'),
        invoiceDate: z.string().trim().optional(),
        currency: z.string().trim().min(1, 'Currency is required.'),
        lines: z
            .array(createInvoiceLineSchema)
            .min(1, 'At least one PO line is required.'),
    })
    .superRefine((values, ctx) => {
        let hasPositiveLine = false;

        values.lines.forEach((line, index) => {
            if (line.qtyInvoiced > 0) {
                hasPositiveLine = true;
            }

            if (line.qtyInvoiced > line.remainingQuantity) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['lines', index, 'qtyInvoiced'],
                    message: 'Cannot invoice more than the remaining quantity.',
                });
            }
        });

        if (!hasPositiveLine) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['lines'],
                message: 'Set a quantity on at least one PO line.',
            });
        }
    });

type CreateInvoiceFormValues = z.infer<typeof createInvoiceFormSchema>;

export interface CreateInvoiceDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    purchaseOrder: Pick<
        PurchaseOrderDetail,
        'id' | 'poNumber' | 'currency' | 'supplierName' | 'supplierId' | 'lines'
    >;
    isSubmitting?: boolean;
    onSubmit: (payload: CreateInvoiceInput) => void;
}

export function CreateInvoiceDialog({
    open,
    onOpenChange,
    purchaseOrder,
    onSubmit,
    isSubmitting = false,
}: CreateInvoiceDialogProps) {
    const defaultValues = useMemo(
        () => buildDefaultValues(purchaseOrder),
        [purchaseOrder],
    );
    const { formatNumber } = useFormatting();

    const form = useForm<CreateInvoiceFormValues>({
        resolver: zodResolver(createInvoiceFormSchema),
        defaultValues,
    });

    useEffect(() => {
        if (open) {
            form.reset(buildDefaultValues(purchaseOrder));
        }
    }, [open, purchaseOrder, form]);

    const lineValues = useWatch({ control: form.control, name: 'lines' }) ?? [];
    const submitDisabled =
        isSubmitting ||
        lineValues.length === 0 ||
        lineValues.every((line) => line.remainingQuantity <= 0);

    const handleSubmit = form.handleSubmit((values) => {
        const filteredLines = values.lines.filter(
            (line) => line.qtyInvoiced > 0,
        );
        const payload: CreateInvoiceInput = {
            poId: purchaseOrder.id,
            supplierId: purchaseOrder.supplierId ?? undefined,
            invoiceNumber: values.invoiceNumber.trim(),
            invoiceDate: values.invoiceDate?.trim() || undefined,
            currency: values.currency.trim().toUpperCase(),
            lines: filteredLines.map((line) => ({
                poLineId: line.poLineId,
                qtyInvoiced: Number(line.qtyInvoiced),
                unitPriceMinor: toMinorUnits(line.unitPrice),
            })),
        };

        onSubmit(payload);
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        Create invoice from PO #{purchaseOrder.poNumber}
                    </DialogTitle>
                    <DialogDescription>
                        {purchaseOrder.supplierName
                            ? `Invoice ${purchaseOrder.supplierName} for fulfilled goods.`
                            : 'Capture supplier invoice details for this PO.'}
                    </DialogDescription>
                    <DocumentNumberPreview docType="invoice" className="mt-3" />
                </DialogHeader>

                <form className="space-y-6" onSubmit={handleSubmit}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="invoice-number">
                                Invoice number
                            </Label>
                            <Input
                                id="invoice-number"
                                placeholder="INV-10025"
                                disabled={isSubmitting}
                                {...form.register('invoiceNumber')}
                            />
                            {form.formState.errors.invoiceNumber ? (
                                <p className="text-sm text-destructive">
                                    {
                                        form.formState.errors.invoiceNumber
                                            .message
                                    }
                                </p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="invoice-date">Invoice date</Label>
                            <Input
                                id="invoice-date"
                                type="date"
                                disabled={isSubmitting}
                                {...form.register('invoiceDate')}
                            />
                            {form.formState.errors.invoiceDate ? (
                                <p className="text-sm text-destructive">
                                    {form.formState.errors.invoiceDate.message}
                                </p>
                            ) : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="invoice-currency">Currency</Label>
                            <Input
                                id="invoice-currency"
                                placeholder="USD"
                                maxLength={3}
                                className="uppercase"
                                disabled={isSubmitting}
                                {...form.register('currency')}
                            />
                            {form.formState.errors.currency ? (
                                <p className="text-sm text-destructive">
                                    {form.formState.errors.currency.message}
                                </p>
                            ) : null}
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label>PO lines to invoice</Label>
                            <p className="text-xs text-muted-foreground">
                                Set quantities up to the remaining amount.
                            </p>
                        </div>

                        <div className="max-h-72 overflow-y-auto rounded-md border border-border/70">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/40 text-xs tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2 text-left">
                                            Line
                                        </th>
                                        <th className="px-3 py-2 text-left">
                                            Remaining
                                        </th>
                                        <th className="px-3 py-2 text-left">
                                            Qty to invoice
                                        </th>
                                        <th className="px-3 py-2 text-left">
                                            Unit price ({purchaseOrder.currency}
                                            )
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lineValues.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-3 py-6 text-center text-muted-foreground"
                                            >
                                                No PO lines available.
                                            </td>
                                        </tr>
                                    ) : (
                                        lineValues.map((line, index) => (
                                            <tr
                                                key={`${line.poLineId}-${index}`}
                                                className="border-t border-border/60"
                                            >
                                                <td className="px-3 py-3 align-top">
                                                    <div className="font-semibold text-foreground">
                                                        Line {line.lineNo}
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {line.description ??
                                                            '—'}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3 align-top">
                                                    <div className="font-semibold text-foreground">
                                                        {formatQuantity(
                                                            line.remainingQuantity ??
                                                                0,
                                                            formatNumber,
                                                        )}
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        units remaining
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        disabled={
                                                            isSubmitting ||
                                                            line.remainingQuantity <=
                                                                0
                                                        }
                                                        {...form.register(
                                                            `lines.${index}.qtyInvoiced` as const,
                                                        )}
                                                    />
                                                    {Array.isArray(
                                                        form.formState.errors
                                                            .lines,
                                                    ) &&
                                                    form.formState.errors.lines[
                                                        index
                                                    ]?.qtyInvoiced ? (
                                                        <p className="pt-1 text-xs text-destructive">
                                                            {
                                                                form.formState
                                                                    .errors
                                                                    .lines[
                                                                    index
                                                                ]?.qtyInvoiced
                                                                    ?.message
                                                            }
                                                        </p>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        disabled={isSubmitting}
                                                        {...form.register(
                                                            `lines.${index}.unitPrice` as const,
                                                        )}
                                                    />
                                                    {Array.isArray(
                                                        form.formState.errors
                                                            .lines,
                                                    ) &&
                                                    form.formState.errors.lines[
                                                        index
                                                    ]?.unitPrice ? (
                                                        <p className="pt-1 text-xs text-destructive">
                                                            {
                                                                form.formState
                                                                    .errors
                                                                    .lines[
                                                                    index
                                                                ]?.unitPrice
                                                                    ?.message
                                                            }
                                                        </p>
                                                    ) : null}
                                                    {line.poUnitPrice != null &&
                                                    Math.abs(
                                                        line.unitPrice -
                                                            line.poUnitPrice,
                                                    ) > 0.001 ? (
                                                        <p className="pt-1 text-xs text-amber-600">
                                                            Differs from PO
                                                            price{' '}
                                                            {formatUnitPrice(
                                                                line.poUnitPrice ??
                                                                    0,
                                                                formatNumber,
                                                            )}
                                                            .
                                                        </p>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {form.formState.errors.lines &&
                        !Array.isArray(form.formState.errors.lines) &&
                        'message' in form.formState.errors.lines ? (
                            <p className="text-sm text-destructive">
                                {form.formState.errors.lines.message as string}
                            </p>
                        ) : null}
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={submitDisabled}>
                            {isSubmitting ? 'Creating…' : 'Create invoice'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function buildDefaultValues(
    purchaseOrder: Pick<
        PurchaseOrderDetail,
        'currency' | 'supplierName' | 'lines' | 'poNumber' | 'id'
    >,
): CreateInvoiceFormValues {
    const today = new Date();
    const isoDate = today.toISOString().slice(0, 10);
    const lines = (purchaseOrder.lines ?? []).map((line) => convertLine(line));

    return {
        invoiceNumber: '',
        invoiceDate: isoDate,
        currency: purchaseOrder.currency ?? 'USD',
        lines,
    };
}

function convertLine(line: PurchaseOrderLine) {
    const remaining = computeRemainingQuantity(line);
    const unitPriceMajor =
        typeof line.unitPriceMinor === 'number'
            ? line.unitPriceMinor / 100
            : typeof line.unitPrice === 'number'
              ? line.unitPrice
              : 0;

    return {
        poLineId: line.id,
        lineNo: line.lineNo,
        description: line.description,
        remainingQuantity: remaining,
        qtyInvoiced: remaining,
        unitPrice: unitPriceMajor,
        poUnitPrice: unitPriceMajor,
    } satisfies CreateInvoiceFormValues['lines'][number];
}

function computeRemainingQuantity(line: PurchaseOrderLine): number {
    if (typeof line.remainingQuantity === 'number') {
        return Math.max(0, line.remainingQuantity);
    }

    const invoiced =
        typeof line.invoicedQuantity === 'number' ? line.invoicedQuantity : 0;
    const total = typeof line.quantity === 'number' ? line.quantity : 0;
    return Math.max(0, total - invoiced);
}

function toMinorUnits(amount: number): number {
    if (!Number.isFinite(amount)) {
        return 0;
    }
    return Math.round(amount * 100);
}

function formatQuantity(
    value: number,
    formatter: FormattingContextValue['formatNumber'],
) {
    const safeValue = Number.isFinite(value) ? value : 0;
    const precision = Math.abs(safeValue) >= 1 ? 2 : 3;
    return formatter(safeValue, {
        maximumFractionDigits: precision,
        fallback: '0',
    });
}

function formatUnitPrice(
    value: number,
    formatter: FormattingContextValue['formatNumber'],
) {
    const safeValue = Number.isFinite(value) ? value : 0;
    return formatter(safeValue, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        fallback: '0.00',
    });
}
