import { z } from 'zod';

export const awardLineSchema = z.object({
    rfqItemId: z.number().int().positive(),
    quoteItemId: z.number().int().positive().optional(),
    awardedQty: z.number().int().positive().optional(),
});

export const awardFormSchema = z.object({
    lines: z.array(awardLineSchema),
});

export type AwardFormValues = z.infer<typeof awardFormSchema>;
export type AwardLineFormValue = z.infer<typeof awardLineSchema>;
