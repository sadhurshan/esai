import { z } from 'zod';

const leadTimeField = z.preprocess(
    (value) => {
        if (typeof value === 'number') {
            return value;
        }
        if (typeof value === 'string' && value.trim().length > 0) {
            const parsed = Number(value);
            return Number.isNaN(parsed) ? undefined : parsed;
        }
        return undefined;
    },
    z
        .number({ required_error: 'Lead time is required' })
        .int('Lead time must be a whole number')
        .min(1, 'Lead time must be at least 1 day')
        .max(3650, 'Lead time must be less than 10 years'),
);

export const supplierRfpProposalSchema = z.object({
    currency: z.string().length(3, 'Select a currency'),
    priceTotal: z
        .string()
        .trim()
        .optional()
        .refine(
            (value) => !value || !Number.isNaN(Number(value)),
            'Enter a valid numeric price (e.g. 12500.00)',
        )
        .refine((value) => !value || Number(value) >= 0, 'Price must be zero or positive'),
    leadTimeDays: leadTimeField,
    approachSummary: z.string().min(20, 'Describe your approach (20+ characters)'),
    scheduleSummary: z.string().min(10, 'Provide a short schedule summary'),
    valueAddSummary: z
        .string()
        .max(2000, 'Limit your value-add notes to 2000 characters')
        .optional()
        .or(z.literal('')),
});

export type SupplierRfpProposalFormValues = z.infer<typeof supplierRfpProposalSchema>;
