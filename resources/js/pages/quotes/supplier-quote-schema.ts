import { z } from 'zod';

const moneyStringSchema = z
    .string({ required_error: 'Unit price is required.' })
    .min(1, 'Unit price is required.')
    .refine((value) => !Number.isNaN(Number(value)), 'Unit price must be numeric.')
    .refine((value) => Number(value) > 0, 'Unit price must be greater than zero.');

const leadTimeStringSchema = z
    .string({ required_error: 'Lead time is required.' })
    .min(1, 'Lead time is required.')
    .refine((value) => !Number.isNaN(Number(value)), 'Lead time must be numeric.')
    .refine((value) => Number.isInteger(Number(value)), 'Lead time must be a whole number.')
    .refine((value) => Number(value) >= 0, 'Lead time cannot be negative.');

const optionalLeadTimeSchema = z
    .union([z.string(), z.number()])
    .transform((value) => {
        if (typeof value === 'number') {
            return Number.isNaN(value) ? '' : String(value);
        }
        return value?.trim() ?? '';
    })
    .refine((value) => value === '' || !Number.isNaN(Number(value)), 'Lead time must be numeric.')
    .refine((value) => value === '' || Number.isInteger(Number(value)), 'Lead time must be a whole number.')
    .refine((value) => value === '' || Number(value) >= 0, 'Lead time cannot be negative.');

const noteSchema = z
    .string()
    .max(500, 'Notes cannot exceed 500 characters.')
    .optional()
    .transform((value) => (value ?? '').trim());

const revisionNoteSchema = z
    .string()
    .max(1000, 'Revision note cannot exceed 1,000 characters.')
    .optional()
    .transform((value) => (value ?? '').trim());

export const supplierQuoteLineSchema = z.object({
    rfqItemId: z.union([z.string(), z.number()]).transform((value) => String(value)),
    quantity: z.number().optional(),
    unitPrice: moneyStringSchema,
    leadTimeDays: leadTimeStringSchema,
    note: noteSchema,
});

export const supplierQuoteFormSchema = z.object({
    currency: z.string().min(1, 'Select a currency.'),
    note: noteSchema,
    leadTimeDays: optionalLeadTimeSchema.optional(),
    revisionNote: revisionNoteSchema,
    attachments: z.array(z.string()).optional().default([]),
    lines: z.array(supplierQuoteLineSchema).min(1, 'Add at least one line.'),
});

export type SupplierQuoteLineFormValues = z.infer<typeof supplierQuoteLineSchema>;
export type SupplierQuoteFormValues = z.infer<typeof supplierQuoteFormSchema>;
