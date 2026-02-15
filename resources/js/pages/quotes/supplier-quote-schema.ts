import { z } from 'zod';

import type { DocumentAttachment } from '@/types/sourcing';

const moneyStringSchema = z
    .string({ required_error: 'Unit price is required.' })
    .min(1, 'Unit price is required.')
    .refine(
        (value) => !Number.isNaN(Number(value)),
        'Unit price must be numeric.',
    )
    .refine(
        (value) => Number(value) > 0,
        'Unit price must be greater than zero.',
    );

const leadTimeStringSchema = z
    .string({ required_error: 'Lead time is required.' })
    .min(1, 'Lead time is required.')
    .refine(
        (value) => !Number.isNaN(Number(value)),
        'Lead time must be numeric.',
    )
    .refine(
        (value) => Number.isInteger(Number(value)),
        'Lead time must be a whole number.',
    )
    .refine((value) => Number(value) >= 0, 'Lead time cannot be negative.');

const optionalLeadTimeSchema = z
    .union([z.string(), z.number()])
    .transform((value) => {
        if (typeof value === 'number') {
            return Number.isNaN(value) ? '' : String(value);
        }
        return value?.trim() ?? '';
    })
    .refine(
        (value) => value === '' || !Number.isNaN(Number(value)),
        'Lead time must be numeric.',
    )
    .refine(
        (value) => value === '' || Number.isInteger(Number(value)),
        'Lead time must be a whole number.',
    )
    .refine(
        (value) => value === '' || Number(value) >= 0,
        'Lead time cannot be negative.',
    );

const noteSchema = z
    .string()
    .max(500, 'Notes cannot exceed 500 characters.')
    .optional()
    .transform((value) => (value ?? '').trim());

const incotermSchema = z
    .string()
    .max(8, 'Incoterm must be 8 characters or fewer.')
    .optional()
    .transform((value) => (value ?? '').trim().toUpperCase());

const paymentTermsSchema = z
    .string()
    .max(120, 'Payment terms cannot exceed 120 characters.')
    .optional()
    .transform((value) => (value ?? '').trim());

const revisionNoteSchema = z
    .string()
    .max(1000, 'Revision note cannot exceed 1,000 characters.')
    .optional()
    .transform((value) => (value ?? '').trim());

const optionalMoqSchema = z
    .union([z.string(), z.number()])
    .transform((value) => {
        if (typeof value === 'number') {
            return Number.isNaN(value) ? '' : String(value);
        }
        return value?.trim() ?? '';
    })
    .refine(
        (value) => value === '' || !Number.isNaN(Number(value)),
        'Minimum order must be numeric.',
    )
    .refine(
        (value) => value === '' || Number.isInteger(Number(value)),
        'Minimum order must be a whole number.',
    )
    .refine(
        (value) => value === '' || Number(value) >= 1,
        'Minimum order must be at least 1.',
    );

const taxCodeIdSchema = z
    .union([z.string(), z.number()])
    .transform((value) => {
        if (typeof value === 'number') {
            return Number.isNaN(value) ? '' : String(value);
        }
        return value?.trim() ?? '';
    })
    .refine(
        (value) => value === '' || /^[0-9]+$/.test(value),
        'Select a valid tax code.',
    );

const taxCodeIdsSchema = z
    .array(taxCodeIdSchema)
    .optional()
    .default([])
    .transform((values) => values.filter((value) => value.length > 0));

const documentAttachmentSchema = z
    .object({
        id: z
            .union([z.string(), z.number()])
            .transform((value) => Number(value))
            .refine(
                (value) => Number.isFinite(value) && value > 0,
                'Attachment id is invalid.',
            ),
        filename: z
            .string()
            .min(1, 'Attachment filename is required.')
            .default('Attachment'),
        mime: z.string().optional().nullable(),
        sizeBytes: z.union([z.string(), z.number()]).optional(),
        downloadUrl: z.string().optional().nullable(),
    })
    .transform(
        (value) =>
            ({
                id: value.id,
                filename: value.filename,
                mime: value.mime ?? 'application/octet-stream',
                sizeBytes:
                    value.sizeBytes !== undefined
                        ? Number(value.sizeBytes) || 0
                        : 0,
                downloadUrl: value.downloadUrl ?? undefined,
            }) as DocumentAttachment,
    );

export const supplierQuoteLineSchema = z.object({
    rfqItemId: z
        .union([z.string(), z.number()])
        .transform((value) => String(value)),
    quantity: z.number().optional(),
    unitPrice: moneyStringSchema,
    leadTimeDays: leadTimeStringSchema,
    note: noteSchema,
    taxCodeIds: taxCodeIdsSchema,
});

export const supplierQuoteFormSchema = z.object({
    currency: z.string().min(1, 'Select a currency.'),
    minOrderQty: optionalMoqSchema.optional(),
    note: noteSchema,
    incoterm: incotermSchema,
    paymentTerms: paymentTermsSchema,
    leadTimeDays: optionalLeadTimeSchema.optional(),
    revisionNote: revisionNoteSchema,
    attachments: z.array(documentAttachmentSchema).optional().default([]),
    lines: z.array(supplierQuoteLineSchema).min(1, 'Add at least one line.'),
});

export type SupplierQuoteLineFormValues = z.infer<
    typeof supplierQuoteLineSchema
>;
export type SupplierQuoteFormValues = z.infer<typeof supplierQuoteFormSchema>;
