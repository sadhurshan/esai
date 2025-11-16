import { useEffect, useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';
import { publishToast } from '@/components/ui/use-toast';
import { NumberingRuleEditor } from '@/components/settings/numbering-rule-editor';
import { useAuth } from '@/contexts/auth-context';
import {
    useNumberingSettings,
    useUpdateNumberingSettings,
    type UpdateNumberingSettingsInput,
} from '@/hooks/api/settings';
import type { NumberingRule, NumberingSettings } from '@/types/settings';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';

const DOC_KEYS: Array<keyof NumberingSettings> = ['rfq', 'quote', 'po', 'invoice', 'grn', 'credit'];

type FormRule = NumberingRule & { sample?: string | null };

const ruleSchema = z.object({
    prefix: z
        .string()
        .max(12, 'Prefixes cannot exceed 12 characters.')
        .default(''),
    sequenceLength: z
        .number({ required_error: 'Sequence length is required.', invalid_type_error: 'Sequence length is required.' })
        .int('Sequence length must be a whole number.')
        .min(3, 'Minimum length is 3.')
        .max(10, 'Maximum length is 10.'),
    next: z
        .number({ required_error: 'Next number is required.', invalid_type_error: 'Next number is required.' })
        .int('Next value must be a whole number.')
        .min(1, 'Next number must be at least 1.'),
    reset: z.enum(['never', 'yearly']),
    sample: z.string().optional().nullable(),
});

const numberingSchema = z.object({
    rfq: ruleSchema,
    quote: ruleSchema,
    po: ruleSchema,
    invoice: ruleSchema,
    grn: ruleSchema,
    credit: ruleSchema,
});

type NumberingFormValues = z.infer<typeof numberingSchema>;

type DocumentMeta = {
    label: string;
    description: string;
};

const DOC_META: Record<keyof NumberingSettings, DocumentMeta> = {
    rfq: { label: 'RFQs', description: 'Requests for Quote share this pattern.' },
    quote: { label: 'Quotes', description: 'Supplier quotes inherit this numbering sequence.' },
    po: { label: 'Purchase Orders', description: 'Applied to buyer-issued POs.' },
    invoice: { label: 'Invoices', description: 'Used when billing customers and internal AP.' },
    grn: { label: 'Goods Receipts', description: 'Receiving documents and inspection logs.' },
    credit: { label: 'Credit Notes', description: 'Refund or correction documents reference this rule.' },
};

const SECTIONS: Array<{ title: string; docs: Array<keyof NumberingSettings> }> = [
    { title: 'Sourcing', docs: ['rfq', 'quote'] },
    { title: 'Purchasing & Receiving', docs: ['po', 'grn'] },
    { title: 'Finance', docs: ['invoice', 'credit'] },
];

const FALLBACK_RULE: FormRule = {
    prefix: '',
    sequenceLength: 4,
    next: 1,
    reset: 'never',
    sample: null,
};

function cloneRule(rule?: NumberingRule): FormRule {
    if (!rule) {
        return { ...FALLBACK_RULE };
    }

    return {
        prefix: rule.prefix ?? '',
        sequenceLength: rule.sequenceLength ?? FALLBACK_RULE.sequenceLength,
        next: rule.next ?? FALLBACK_RULE.next,
        reset: rule.reset ?? FALLBACK_RULE.reset,
        sample: rule.sample ?? null,
    } satisfies FormRule;
}

function toFormValues(settings?: NumberingSettings): NumberingFormValues {
    return DOC_KEYS.reduce((acc, key) => {
        acc[key] = cloneRule(settings?.[key]);
        return acc;
    }, {} as NumberingFormValues);
}

function buildPayload(values: NumberingFormValues): UpdateNumberingSettingsInput {
    return DOC_KEYS.reduce((acc, key) => {
        const rule = values[key];
        acc[key] = {
            prefix: rule.prefix.trim(),
            sequenceLength: rule.sequenceLength,
            next: rule.next,
            reset: rule.reset,
        } satisfies UpdateNumberingSettingsInput[keyof UpdateNumberingSettingsInput];
        return acc;
    }, {} as UpdateNumberingSettingsInput);
}

interface PreviewRow {
    key: keyof NumberingSettings;
    sample: string;
    reset: string;
    warning?: string;
}

function padSample(rule?: FormRule): string {
    if (!rule) {
        return 'PFX-0001';
    }

    const next = Number.isFinite(rule.next) ? Math.max(1, Math.trunc(rule.next)) : 1;
    const padding = Math.max(3, Math.min(10, rule.sequenceLength ?? 3));
    const padded = String(next).padStart(padding, '0');
    return `${rule.prefix ?? ''}${padded}`;
}

function buildPreview(values: NumberingFormValues): PreviewRow[] {
    return DOC_KEYS.map((key) => {
        const rule = values[key];
        const sample = padSample(rule);
        const overflow = rule.sequenceLength ? String(rule.next ?? '').length > rule.sequenceLength : false;
        return {
            key,
            sample,
            reset: rule.reset,
            warning: overflow
                ? 'Next number exceeds the available padding. Increase the sequence length or reset cadence.'
                : undefined,
        } satisfies PreviewRow;
    });
}

export function NumberingSettingsPage() {
    const { isAdmin } = useAuth();
    const numberingQuery = useNumberingSettings();
    const updateNumbering = useUpdateNumberingSettings();

    const form = useForm<NumberingFormValues>({
        resolver: zodResolver(numberingSchema),
        defaultValues: toFormValues(numberingQuery.data),
    });

    useEffect(() => {
        if (numberingQuery.data) {
            form.reset(toFormValues(numberingQuery.data));
        }
    }, [numberingQuery.data, form]);

    const watchedValues = (useWatch<NumberingFormValues>({ control: form.control }) ?? form.getValues()) as NumberingFormValues;
    const preview = useMemo(() => buildPreview(watchedValues), [watchedValues]);

    const handleSubmit = form.handleSubmit(async (values) => {
        try {
            await updateNumbering.mutateAsync(buildPayload(values));
            publishToast({
                variant: 'success',
                title: 'Numbering rules saved',
                description: 'New documents will now follow the updated numbering patterns.',
            });
        } catch (error) {
            void error;
            publishToast({
                variant: 'destructive',
                title: 'Unable to save numbering rules',
                description: 'Please resolve the highlighted fields and try again.',
            });
        }
    });

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const isLoading = numberingQuery.isLoading && !numberingQuery.data;

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Numbering settings · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">Workspace · Settings</p>
                <h1 className="text-2xl font-semibold tracking-tight">Document numbering</h1>
                <p className="text-sm text-muted-foreground">
                    Align RFQs, purchase orders, invoices, and receiving docs with the prefixes and reset cadences your ERP expects.
                </p>
            </div>
            {isLoading ? (
                <Skeleton className="h-96 w-full" />
            ) : (
                <Form {...form}>
                    <form onSubmit={handleSubmit} className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                        <div className="space-y-6">
                            {SECTIONS.map((section) => (
                                <Card key={section.title}>
                                    <CardHeader>
                                        <CardTitle>{section.title}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {section.docs.map((docKey) => (
                                            <NumberingRuleEditor
                                                key={docKey}
                                                control={form.control}
                                                name={docKey}
                                                label={DOC_META[docKey].label}
                                                description={DOC_META[docKey].description}
                                            />
                                        ))}
                                    </CardContent>
                                    {section.docs[section.docs.length - 1] === 'credit' ? (
                                        <CardFooter className="justify-end">
                                            <Button type="submit" disabled={updateNumbering.isPending}>
                                                {updateNumbering.isPending ? 'Saving…' : 'Save numbering rules'}
                                            </Button>
                                        </CardFooter>
                                    ) : null}
                                </Card>
                            ))}
                        </div>
                        <div className="space-y-4">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Live preview</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    {preview.map((row) => (
                                        <div key={row.key} className="rounded-lg border p-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <div>
                                                    <p className="font-medium">{DOC_META[row.key].label}</p>
                                                    <p className="text-xs text-muted-foreground">{DOC_META[row.key].description}</p>
                                                </div>
                                                <Badge variant="outline">
                                                    {row.reset === 'yearly' ? 'Resets yearly' : 'Continuous'}
                                                </Badge>
                                            </div>
                                            <p className="mt-2 font-mono text-lg">{row.sample}</p>
                                            {row.warning ? (
                                                <p className="text-xs text-destructive">{row.warning}</p>
                                            ) : null}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Tips</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm text-muted-foreground">
                                    <div>
                                        <Label className="text-xs uppercase text-muted-foreground">Sequence padding</Label>
                                        <p>Use at least 4 digits so yearly volumes do not collide.</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs uppercase text-muted-foreground">Prefixes</Label>
                                        <p>Keep prefixes short (≤12 characters) to avoid truncated document titles.</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs uppercase text-muted-foreground">Reset cadence</Label>
                                        <p>Select yearly resets if accounting requires {`YYYY-0001`} style sequences.</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </form>
                </Form>
            )}
        </div>
    );
}
