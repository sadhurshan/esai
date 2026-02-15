import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import {
    Controller,
    useFieldArray,
    useForm,
    useWatch,
    type UseFormReturn,
} from 'react-hook-form';
import { useLocation, useNavigate } from 'react-router-dom';
import { z } from 'zod';

import { DocumentNumberPreview } from '@/components/documents/document-number-preview';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { publishToast } from '@/components/ui/use-toast';
import {
    RFQ_METHOD_OPTIONS,
    RFQ_METHOD_VALUES,
    getRfqMethodLabel,
    isRfqMethod,
    type RfqMethod,
} from '@/constants/rfq';
import { useAuth } from '@/contexts/auth-context';
import {
    useCreateRfq,
    useInviteSuppliers,
    usePublishRfq,
    useUploadAttachment,
} from '@/hooks/api/rfqs';
import type { CreateRfqPayload } from '@/hooks/api/rfqs/use-create-rfq';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import { useUoms } from '@/hooks/api/use-uoms';
import { useBaseUomQuantity } from '@/hooks/use-uom-conversion-helper';
import { consumeLowStockRfqPrefill } from '@/lib/low-stock-rfq-prefill';
import { buildCurrencyOptions, getDefaultCurrency } from '@/lib/money';
import {
    buildRfqAssistSignature,
    buildRfqAssistSuggestions,
    type RfqAssistSuggestions,
} from '@/lib/rfq-assist';
import type { DigitalTwinUseForRfqDraft } from '@/sdk';
import type { Supplier } from '@/types/sourcing';

const lineSchema = z.object({
    partName: z.string().min(1, 'Part name is required.'),
    spec: z.string().optional(),
    method: z.string().min(1, 'Manufacturing method is required.'),
    material: z.string().min(1, 'Material is required.'),
    tolerance: z.string().optional(),
    finish: z.string().optional(),
    quantity: z.coerce
        .number({ invalid_type_error: 'Quantity must be numeric.' })
        .positive('Quantity must be greater than zero.'),
    uom: z.string().min(1, 'UoM is required.'),
    targetPrice: z
        .union([
            z.coerce.number({
                invalid_type_error: 'Target price must be numeric.',
            }),
            z.literal(''),
        ])
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
});

const supplierSelectionSchema = z.object({
    id: z.string(),
    name: z.string(),
    location: z.string().optional(),
    methods: z.array(z.string()).optional(),
});

const wizardSchema = z
    .object({
        title: z.string().min(1, 'Title is required.'),
        summary: z.string().optional(),
        method: z.enum(RFQ_METHOD_VALUES),
        deliveryLocation: z.string().min(1, 'Delivery location is required.'),
        openBidding: z.boolean().default(false),
        notes: z.string().optional(),
        lines: z.array(lineSchema).min(1, 'Add at least one line item.'),
        suppliers: z.array(supplierSelectionSchema).default([]),
        publishNow: z.boolean().default(false),
        incoterm: z.string().optional(),
        paymentTerms: z.string().optional(),
        taxPercent: z
            .union([
                z.coerce
                    .number({ invalid_type_error: 'Tax rate must be numeric.' })
                    .min(0, 'Tax rate cannot be negative.')
                    .max(100, 'Tax rate must be 100% or less.'),
                z.literal(''),
            ])
            .optional()
            .transform((value) => (value === '' ? undefined : value)),
        currency: z
            .string()
            .length(3, 'Select a currency.')
            .regex(/^[A-Za-z]{3}$/i, 'Currency codes must use three letters.')
            .transform((value) => value.toUpperCase())
            .default('USD'),
        publishAt: z
            .string()
            .optional()
            .refine((value) => {
                if (!value || value.length === 0) {
                    return true;
                }

                const parsed = new Date(value);
                return !Number.isNaN(parsed.getTime());
            }, 'Publish date is invalid.'),
        dueDate: z
            .string()
            .min(1, 'Quote submission deadline is required.')
            .refine((value) => {
                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) {
                    return false;
                }

                return parsed > new Date();
            }, 'Quote submission deadline must be in the future.'),
    })
    .superRefine((values, ctx) => {
        if (!values.dueDate) {
            return;
        }

        const dueDate = new Date(values.dueDate);
        if (Number.isNaN(dueDate.getTime())) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['dueDate'],
                message: 'Enter a valid quote submission deadline.',
            });
            return;
        }

        if (values.publishAt && values.publishAt.length > 0) {
            const publishAt = new Date(values.publishAt);
            if (Number.isNaN(publishAt.getTime())) {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    path: ['publishAt'],
                    message: 'Publish date is invalid.',
                });
            } else {
                if (publishAt > dueDate) {
                    ctx.addIssue({
                        code: z.ZodIssueCode.custom,
                        path: ['publishAt'],
                        message:
                            'Publish date must be before the quote submission deadline.',
                    });
                }

                const now = new Date();
                if (publishAt < now) {
                    ctx.addIssue({
                        code: z.ZodIssueCode.custom,
                        path: ['publishAt'],
                        message: 'Publish date cannot be in the past.',
                    });
                }
            }
        }
    });

type WizardSupplier = z.infer<typeof supplierSelectionSchema>;
type WizardFormValues = z.infer<typeof wizardSchema>;

type RfqAssistSummary = {
    suggestions: Array<{
        field: string;
        value: string;
        source: string;
        note: string;
    }>;
    conflicts: string[];
    missing: string[];
};

const STEPS = [
    { id: 'basics', title: 'Basics' },
    { id: 'lines', title: 'Lines' },
    { id: 'suppliers', title: 'Suppliers' },
    { id: 'terms', title: 'Dates & Terms' },
    { id: 'attachments', title: 'Attachments' },
    { id: 'review', title: 'Review & Publish' },
] as const;

type StepId = (typeof STEPS)[number]['id'];

const LOCAL_STORAGE_KEY = 'esai.rfq-wizard-state';

type AttachmentKey = `${string}-${number}-${number}`;

const STEP_FIELDS: Record<StepId, (keyof WizardFormValues)[]> = {
    basics: [
        'title',
        'summary',
        'method',
        'deliveryLocation',
        'notes',
        'openBidding',
    ],
    lines: ['lines'],
    suppliers: ['suppliers'],
    terms: [
        'publishAt',
        'dueDate',
        'incoterm',
        'paymentTerms',
        'taxPercent',
        'currency',
    ],
    attachments: [],
    review: ['publishNow'],
};

function parseLegacySupplierList(input?: string): string[] {
    if (!input) {
        return [];
    }

    return Array.from(
        new Set(
            input
                .split(/\r?\n|,/)
                .map((entry) => entry.trim())
                .filter((entry) => entry.length > 0),
        ),
    );
}

function normalizeStoredSupplier(
    selection: Partial<WizardSupplier> | undefined,
): WizardSupplier | null {
    if (!selection || !selection.id) {
        return null;
    }

    const methods = Array.isArray(selection.methods)
        ? selection.methods
              .filter(
                  (method): method is string =>
                      typeof method === 'string' && method.length > 0,
              )
              .slice(0, 3)
        : undefined;

    return {
        id: String(selection.id),
        name: selection.name ?? `Supplier ${selection.id}`,
        location: selection.location ?? undefined,
        methods,
    } satisfies WizardSupplier;
}

function formatSupplierLocation(supplier: Supplier): string | undefined {
    const city = supplier.address.city?.trim();
    const country = supplier.address.country?.trim();
    if (city && country) {
        return `${city}, ${country}`;
    }

    return city || country || undefined;
}

function buildSupplierSelection(supplier: Supplier): WizardSupplier {
    const methods =
        supplier.capabilities.methods &&
        supplier.capabilities.methods.length > 0
            ? supplier.capabilities.methods.slice(0, 3)
            : undefined;

    return {
        id: String(supplier.id),
        name: supplier.name,
        location: formatSupplierLocation(supplier),
        methods,
    } satisfies WizardSupplier;
}

interface WizardLineFieldsProps {
    index: number;
    form: UseFormReturn<WizardFormValues>;
    onRemove: () => void;
    canRemove: boolean;
}

function WizardLineFields({
    index,
    form,
    onRemove,
    canRemove,
}: WizardLineFieldsProps) {
    const { register, formState, control } = form;
    const quantity = useWatch({ control, name: `lines.${index}.quantity` });
    const uom = useWatch({ control, name: `lines.${index}.uom` });
    const { baseUomLabel, convertedLabel, isEnabled, isLoading } =
        useBaseUomQuantity(quantity, uom);
    const uomQuery = useUoms({ enabled: isEnabled });
    const uomOptions = useMemo(() => {
        const data = uomQuery.data ?? [];
        return data.map((item) => {
            const shorthand =
                item.symbol && item.symbol.length > 0
                    ? item.symbol.toUpperCase()
                    : item.code.toUpperCase();
            return {
                ...item,
                shorthand,
                description: `${item.name}${item.siBase ? ' - Base unit' : ''}`,
            };
        });
    }, [uomQuery.data]);
    const showUomSelect =
        isEnabled &&
        !uomQuery.isError &&
        (uomQuery.isLoading || uomOptions.length > 0);
    const conversionMessage = useMemo(() => {
        if (!isEnabled) {
            return null;
        }

        if (isLoading) {
            return 'Converting quantity to base unit...';
        }

        if (convertedLabel) {
            return `Approximately ${convertedLabel} ${baseUomLabel} (base unit)`;
        }

        return 'Conversion unavailable for the selected UoM.';
    }, [baseUomLabel, convertedLabel, isEnabled, isLoading]);

    const lineErrors = formState.errors.lines?.[index];

    return (
        <div className="rounded-lg border p-4">
            <div className="grid gap-2">
                <Label htmlFor={`lines.${index}.partName`}>
                    Part / description
                </Label>
                <Input
                    id={`lines.${index}.partName`}
                    placeholder={`Line ${index + 1} description`}
                    {...register(`lines.${index}.partName`)}
                />
                {lineErrors?.partName ? (
                    <p className="text-sm text-destructive">
                        {lineErrors.partName.message}
                    </p>
                ) : null}
            </div>

            <div className="mt-3 grid gap-2">
                <Label htmlFor={`lines.${index}.spec`}>Specification</Label>
                <Textarea
                    id={`lines.${index}.spec`}
                    rows={3}
                    placeholder="Attach tolerances, inspection checkpoints, or reference drawings."
                    {...register(`lines.${index}.spec`)}
                />
            </div>

            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.method`}>
                        Manufacturing method
                    </Label>
                    <Input
                        id={`lines.${index}.method`}
                        placeholder="CNC machining"
                        {...register(`lines.${index}.method`)}
                        aria-invalid={lineErrors?.method ? 'true' : undefined}
                    />
                    {lineErrors?.method ? (
                        <p className="text-sm text-destructive">
                            {lineErrors.method.message}
                        </p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.material`}>Material</Label>
                    <Input
                        id={`lines.${index}.material`}
                        placeholder="6061-T6 Aluminum"
                        {...register(`lines.${index}.material`)}
                        aria-invalid={lineErrors?.material ? 'true' : undefined}
                    />
                    {lineErrors?.material ? (
                        <p className="text-sm text-destructive">
                            {lineErrors.material.message}
                        </p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.tolerance`}>
                        Tolerance (optional)
                    </Label>
                    <Input
                        id={`lines.${index}.tolerance`}
                        placeholder="±0.05 mm"
                        {...register(`lines.${index}.tolerance`)}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.finish`}>
                        Finish (optional)
                    </Label>
                    <Input
                        id={`lines.${index}.finish`}
                        placeholder="Anodized"
                        {...register(`lines.${index}.finish`)}
                    />
                </div>
            </div>

            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.quantity`}>Quantity</Label>
                    <Input
                        id={`lines.${index}.quantity`}
                        type="number"
                        min="1"
                        step="1"
                        {...register(`lines.${index}.quantity`)}
                    />
                    {lineErrors?.quantity ? (
                        <p className="text-sm text-destructive">
                            {lineErrors.quantity.message}
                        </p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.uom`}>UoM</Label>
                    {showUomSelect ? (
                        <Controller
                            control={control}
                            name={`lines.${index}.uom`}
                            render={({ field }) => {
                                const value =
                                    typeof field.value === 'string'
                                        ? field.value
                                        : '';
                                const normalizedValue = value.toLowerCase();
                                const options =
                                    value &&
                                    !uomOptions.some(
                                        (option) =>
                                            option.code.toLowerCase() ===
                                            normalizedValue,
                                    )
                                        ? [
                                              ...uomOptions,
                                              {
                                                  code: value,
                                                  shorthand:
                                                      value.toUpperCase(),
                                                  description: value,
                                                  dimension: '',
                                                  siBase: false,
                                                  symbol: value,
                                                  name: value,
                                              },
                                          ]
                                        : uomOptions;

                                const handleChange = (next: string) => {
                                    field.onChange(next);
                                    field.onBlur();
                                };

                                return (
                                    <Select
                                        value={value}
                                        onValueChange={handleChange}
                                        disabled={uomQuery.isLoading}
                                    >
                                        <SelectTrigger
                                            id={`lines.${index}.uom`}
                                            aria-invalid={
                                                lineErrors?.uom
                                                    ? 'true'
                                                    : undefined
                                            }
                                        >
                                            <SelectValue
                                                placeholder={
                                                    uomQuery.isLoading
                                                        ? 'Loading units...'
                                                        : 'Select unit'
                                                }
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.map((option) => (
                                                <SelectItem
                                                    key={option.code}
                                                    value={option.code}
                                                >
                                                    <div className="flex w-full flex-col gap-0.5">
                                                        <span className="text-sm font-medium text-foreground">
                                                            {option.shorthand}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {option.description}
                                                        </span>
                                                    </div>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                );
                            }}
                        />
                    ) : (
                        <Input
                            id={`lines.${index}.uom`}
                            placeholder="ea"
                            {...register(`lines.${index}.uom`)}
                            aria-invalid={lineErrors?.uom ? 'true' : undefined}
                        />
                    )}
                    {lineErrors?.uom ? (
                        <p className="text-sm text-destructive">
                            {lineErrors.uom.message}
                        </p>
                    ) : null}
                    {isEnabled && uomQuery.isError ? (
                        <p className="text-xs text-muted-foreground">
                            Unable to load the unit catalog right now. Enter a
                            custom unit code if needed.
                        </p>
                    ) : null}
                    {conversionMessage ? (
                        <p className="text-xs text-muted-foreground">
                            {conversionMessage}
                        </p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.requiredDate`}>
                        Required date
                    </Label>
                    <Input
                        id={`lines.${index}.requiredDate`}
                        type="date"
                        {...register(`lines.${index}.requiredDate`)}
                    />
                    {lineErrors?.requiredDate ? (
                        <p className="text-sm text-destructive">
                            {lineErrors.requiredDate.message}
                        </p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.targetPrice`}>
                        Target price (optional)
                    </Label>
                    <Input
                        id={`lines.${index}.targetPrice`}
                        type="number"
                        step="0.01"
                        {...register(`lines.${index}.targetPrice`)}
                    />
                </div>
            </div>

            {canRemove ? (
                <div className="mt-3 text-right">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onRemove}
                    >
                        Remove line
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

function createDefaultWizardValues(): WizardFormValues {
    return {
        title: '',
        summary: '',
        method: RFQ_METHOD_VALUES[0],
        deliveryLocation: '',
        openBidding: false,
        notes: '',
        lines: [
            {
                partName: '',
                spec: '',
                method: '',
                material: '',
                tolerance: '',
                finish: '',
                quantity: 1,
                uom: 'ea',
                targetPrice: undefined,
                requiredDate: '',
            },
        ],
        suppliers: [],
        publishNow: false,
        incoterm: '',
        paymentTerms: '',
        taxPercent: undefined,
        currency: 'USD',
        publishAt: '',
        dueDate: '',
    } satisfies WizardFormValues;
}

function isDigitalTwinDraft(
    value: unknown,
): value is DigitalTwinUseForRfqDraft {
    if (!value || typeof value !== 'object') {
        return false;
    }

    return Array.isArray((value as DigitalTwinUseForRfqDraft).lines);
}

function extractDigitalTwinDraft(
    state: unknown,
): DigitalTwinUseForRfqDraft | null {
    if (!state || typeof state !== 'object') {
        return null;
    }

    if (!('digitalTwinDraft' in state)) {
        return null;
    }

    const payload = (state as Record<string, unknown>).digitalTwinDraft;
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    if (
        'draft' in payload &&
        isDigitalTwinDraft((payload as Record<string, unknown>).draft)
    ) {
        return (payload as { draft: DigitalTwinUseForRfqDraft }).draft;
    }

    return isDigitalTwinDraft(payload)
        ? (payload as DigitalTwinUseForRfqDraft)
        : null;
}

function mapDigitalTwinDraftLines(
    lines: DigitalTwinUseForRfqDraft['lines'],
    fallbackTitle?: string | null,
): WizardFormValues['lines'] {
    if (!Array.isArray(lines) || lines.length === 0) {
        return [];
    }

    return lines.map((line, index) => ({
        partName: line.part_name ?? fallbackTitle ?? `Line ${index + 1}`,
        spec: line.spec ?? '',
        method: line.method ?? '',
        material: line.material ?? '',
        tolerance: line.tolerance ?? '',
        finish: line.finish ?? '',
        quantity: line.quantity && line.quantity > 0 ? line.quantity : 1,
        uom: line.uom && line.uom.length > 0 ? line.uom : 'ea',
        targetPrice: line.target_price ?? undefined,
        requiredDate: line.required_date ?? '',
    }));
}

function omitDigitalTwinDraftState(
    state: unknown,
): Record<string, unknown> | undefined {
    if (!state || typeof state !== 'object') {
        return undefined;
    }

    if (!('digitalTwinDraft' in state)) {
        return state as Record<string, unknown>;
    }

    const rest = { ...(state as Record<string, unknown>) };
    delete rest.digitalTwinDraft;
    return Object.keys(rest).length > 0 ? rest : undefined;
}

function formatDigitalTwinSpecValue(
    value?: string | null,
    uom?: string | null,
): string {
    if (!value || value.trim().length === 0) {
        return '—';
    }

    return uom ? `${value} ${uom}` : value;
}

function formatFileSize(bytes?: number | null): string {
    if (typeof bytes !== 'number' || Number.isNaN(bytes) || bytes <= 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});

function formatDateLabel(value?: string, includeTime = false): string {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return includeTime
        ? dateTimeFormatter.format(parsed)
        : dateFormatter.format(parsed);
}

type RfqAssistField =
    | 'method'
    | 'material'
    | 'finish'
    | 'quantity'
    | 'lead_time';

const RFQ_ASSIST_LABELS: Record<RfqAssistField, string> = {
    method: 'Process',
    material: 'Material',
    finish: 'Finish',
    quantity: 'Quantity',
    lead_time: 'Lead time (days)',
};

const normalizeValue = (value?: string | number | null): string =>
    typeof value === 'number' ? String(value) : (value ?? '').trim();

const valuesMatch = (
    left?: string | number | null,
    right?: string | number | null,
): boolean => {
    if (typeof left === 'number' || typeof right === 'number') {
        return Number(left) === Number(right);
    }

    return (
        normalizeValue(left).toLowerCase() ===
        normalizeValue(right).toLowerCase()
    );
};

const buildRequiredDateFromLeadTime = (days: number): string => {
    const target = new Date();
    target.setDate(target.getDate() + days);
    return target.toISOString().slice(0, 10);
};

const buildRfqAssistSummary = (
    suggestions: RfqAssistSuggestions,
    lines: WizardFormValues['lines'],
): RfqAssistSummary => {
    const suggestionList: RfqAssistSummary['suggestions'] = [];
    const conflicts: string[] = [];
    const missing: string[] = [];

    if (suggestions.method) {
        suggestionList.push({
            field: RFQ_ASSIST_LABELS.method,
            value: suggestions.method.value,
            source: suggestions.method.source,
            note: suggestions.method.note,
        });
    }
    if (suggestions.material) {
        suggestionList.push({
            field: RFQ_ASSIST_LABELS.material,
            value: suggestions.material.value,
            source: suggestions.material.source,
            note: suggestions.material.note,
        });
    }
    if (suggestions.finish) {
        suggestionList.push({
            field: RFQ_ASSIST_LABELS.finish,
            value: suggestions.finish.value,
            source: suggestions.finish.source,
            note: suggestions.finish.note,
        });
    }
    if (suggestions.quantity) {
        suggestionList.push({
            field: RFQ_ASSIST_LABELS.quantity,
            value: String(suggestions.quantity.value),
            source: suggestions.quantity.source,
            note: suggestions.quantity.note,
        });
    }
    if (suggestions.leadTimeDays) {
        suggestionList.push({
            field: RFQ_ASSIST_LABELS.lead_time,
            value: String(suggestions.leadTimeDays.value),
            source: suggestions.leadTimeDays.source,
            note: suggestions.leadTimeDays.note,
        });
    }

    lines.forEach((line, index) => {
        const lineLabel = `Line ${index + 1}`;

        if (
            suggestions.method &&
            line.method &&
            !valuesMatch(line.method, suggestions.method.value)
        ) {
            conflicts.push(`${lineLabel}: process differs from suggestion.`);
        }
        if (
            suggestions.material &&
            line.material &&
            !valuesMatch(line.material, suggestions.material.value)
        ) {
            conflicts.push(`${lineLabel}: material differs from suggestion.`);
        }
        if (
            suggestions.finish &&
            line.finish &&
            !valuesMatch(line.finish, suggestions.finish.value)
        ) {
            conflicts.push(`${lineLabel}: finish differs from suggestion.`);
        }
        if (
            suggestions.quantity &&
            line.quantity &&
            !valuesMatch(line.quantity, suggestions.quantity.value)
        ) {
            conflicts.push(`${lineLabel}: quantity differs from suggestion.`);
        }
        if (suggestions.leadTimeDays && line.requiredDate) {
            const expectedDate = buildRequiredDateFromLeadTime(
                suggestions.leadTimeDays.value,
            );
            if (!valuesMatch(line.requiredDate, expectedDate)) {
                conflicts.push(
                    `${lineLabel}: required date differs from lead time suggestion.`,
                );
            }
        }

        if (!normalizeValue(line.method)) {
            missing.push(`${lineLabel}: process`);
        }
        if (!normalizeValue(line.material)) {
            missing.push(`${lineLabel}: material`);
        }
        if (!normalizeValue(line.finish)) {
            missing.push(`${lineLabel}: finish`);
        }
        if (!line.quantity || line.quantity <= 0) {
            missing.push(`${lineLabel}: quantity`);
        }
        if (!normalizeValue(line.requiredDate)) {
            missing.push(`${lineLabel}: lead time`);
        }
    });

    return {
        suggestions: suggestionList,
        conflicts: Array.from(new Set(conflicts)),
        missing: Array.from(new Set(missing)),
    };
};

const applyRfqAssistToLines = (
    lines: WizardFormValues['lines'],
    suggestions: RfqAssistSuggestions,
    dirtyFields: unknown,
    allowApply: boolean,
): { nextLines: WizardFormValues['lines']; applied: boolean } => {
    if (!allowApply) {
        return { nextLines: lines, applied: false };
    }

    const dirtyLines =
        (dirtyFields as { lines?: Array<Record<string, boolean> | undefined> })
            ?.lines ?? [];
    let applied = false;

    const nextLines = lines.map((line, index) => {
        const next = { ...line };
        const dirty = dirtyLines[index] ?? {};

        if (
            suggestions.method &&
            !normalizeValue(next.method) &&
            !dirty.method
        ) {
            next.method = suggestions.method.value;
            applied = true;
        }
        if (
            suggestions.material &&
            !normalizeValue(next.material) &&
            !dirty.material
        ) {
            next.material = suggestions.material.value;
            applied = true;
        }
        if (
            suggestions.finish &&
            !normalizeValue(next.finish) &&
            !dirty.finish
        ) {
            next.finish = suggestions.finish.value;
            applied = true;
        }
        if (
            suggestions.quantity &&
            (!next.quantity ||
                next.quantity <= 0 ||
                (!dirty.quantity && next.quantity === 1))
        ) {
            next.quantity = suggestions.quantity.value;
            applied = true;
        }
        if (
            suggestions.leadTimeDays &&
            !normalizeValue(next.requiredDate) &&
            !dirty.requiredDate
        ) {
            next.requiredDate = buildRequiredDateFromLeadTime(
                suggestions.leadTimeDays.value,
            );
            applied = true;
        }

        return next;
    });

    return { nextLines, applied };
};

export function RfqCreateWizard() {
    const [stepIndex, setStepIndex] = useState(0);
    const [attachments, setAttachments] = useState<File[]>([]);
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [isSupplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const [digitalTwinPrefill, setDigitalTwinPrefill] =
        useState<DigitalTwinUseForRfqDraft | null>(null);
    const [showDigitalTwinBanner, setShowDigitalTwinBanner] = useState(false);
    const [rfqAssistSummary, setRfqAssistSummary] =
        useState<RfqAssistSummary | null>(null);
    const [rfqAssistDismissed, setRfqAssistDismissed] = useState(false);
    const digitalTwinPrefillApplied = useRef(false);
    const rfqAssistSignature = useRef<string | null>(null);
    const { hasFeature, state: authState } = useAuth();
    const createRfqMutation = useCreateRfq();
    const inviteSuppliersMutation = useInviteSuppliers();
    const publishRfqMutation = usePublishRfq();
    const uploadAttachmentMutation = useUploadAttachment();
    const navigate = useNavigate();
    const location = useLocation();

    const featureFlagsLoaded =
        Object.keys(authState.featureFlags ?? {}).length > 0;
    const allowFeature = (key: string) =>
        featureFlagsLoaded ? hasFeature(key) : true;

    const canCreateRfq = allowFeature('rfqs.create');
    const canPublishRfq = allowFeature('rfqs.publish');
    const canInviteSuppliers = allowFeature('rfqs.suppliers.invite');
    const canManageAttachments = allowFeature('rfqs.attachments.manage');
    const canBrowseSupplierDirectory = allowFeature(
        'suppliers.directory.browse',
    );
    const canUseSupplierDirectory =
        canInviteSuppliers && canBrowseSupplierDirectory;

    const moneySettingsQuery = useMoneySettings();
    const currencyOptions = useMemo(
        () => buildCurrencyOptions(moneySettingsQuery.data),
        [moneySettingsQuery.data],
    );
    const defaultCurrency = getDefaultCurrency(currencyOptions);

    const form = useForm<WizardFormValues>({
        resolver: zodResolver(wizardSchema),
        defaultValues: createDefaultWizardValues(),
        mode: 'onChange',
    });

    const { control } = form;
    const currencyValue = useWatch({ control, name: 'currency' }) ?? '';
    const titleValue = useWatch({ control, name: 'title' }) ?? '';
    const selectedSuppliers = (useWatch({ control, name: 'suppliers' }) ??
        []) as WizardSupplier[];
    const rawLineValues = useWatch({ control, name: 'lines' }) as
        | WizardFormValues['lines']
        | undefined;
    const lineValues = useMemo(() => rawLineValues ?? [], [rawLineValues]);
    const linesFieldArray = useFieldArray({ control, name: 'lines' });
    const replaceLineItems = linesFieldArray.replace;
    const [prefillContext, setPrefillContext] = useState<{
        count: number;
    } | null>(null);
    const lowStockPrefillApplied = useRef(false);
    const reviewSnapshot = useWatch<WizardFormValues>({ control });
    const isSubmitting =
        isFinalizing ||
        createRfqMutation.isPending ||
        inviteSuppliersMutation.isPending ||
        publishRfqMutation.isPending ||
        uploadAttachmentMutation.isPending;

    useEffect(() => {
        if (!canCreateRfq) {
            return;
        }

        if (typeof window === 'undefined') {
            return;
        }

        try {
            const raw = window.localStorage.getItem(LOCAL_STORAGE_KEY);
            if (!raw) {
                return;
            }

            const stored = JSON.parse(raw) as Partial<WizardFormValues> & {
                supplierInputs?: string;
                type?: string;
                clientCompany?: string;
            };
            const restoredLines =
                stored.lines && stored.lines.length > 0
                    ? stored.lines.map((line) => ({
                          ...line,
                          requiredDate: line.requiredDate ?? '',
                      }))
                    : form.getValues().lines;

            const currentValues = form.getValues();
            const normalizedMethod = isRfqMethod(stored.method)
                ? stored.method
                : isRfqMethod(stored.type)
                  ? stored.type
                  : currentValues.method;
            const normalizedDeliveryLocation =
                stored.deliveryLocation ??
                stored.clientCompany ??
                currentValues.deliveryLocation;

            const normalizedSuppliers: WizardSupplier[] = Array.isArray(
                stored.suppliers,
            )
                ? stored.suppliers
                      .map((entry) => normalizeStoredSupplier(entry))
                      .filter((entry): entry is WizardSupplier =>
                          Boolean(entry),
                      )
                : [];

            if (
                normalizedSuppliers.length === 0 &&
                typeof stored.supplierInputs === 'string'
            ) {
                const legacyIds = parseLegacySupplierList(
                    stored.supplierInputs,
                );
                normalizedSuppliers.push(
                    ...legacyIds.map((id) => ({
                        id,
                        name: id,
                    })),
                );
            }

            form.reset({
                ...currentValues,
                ...stored,
                method: normalizedMethod,
                deliveryLocation: normalizedDeliveryLocation,
                lines: restoredLines,
                suppliers: normalizedSuppliers,
                publishAt: stored.publishAt ?? form.getValues().publishAt,
                dueDate: stored.dueDate ?? form.getValues().dueDate,
            });
        } catch (error) {
            console.warn(
                'Failed to restore RFQ wizard draft from storage',
                error,
            );
        }
    }, [canCreateRfq, form]);

    useEffect(() => {
        if (!defaultCurrency) {
            return;
        }

        const currentCurrency = form.getValues('currency');
        const hasMatch =
            typeof currentCurrency === 'string' &&
            currencyOptions.some((option) => option.value === currentCurrency);

        if (!hasMatch) {
            form.setValue('currency', defaultCurrency, {
                shouldDirty: false,
                shouldTouch: false,
            });
        }
    }, [currencyOptions, defaultCurrency, form]);

    useEffect(() => {
        if (lowStockPrefillApplied.current) {
            return;
        }

        const prefills = consumeLowStockRfqPrefill();
        if (!prefills || prefills.length === 0) {
            return;
        }

        lowStockPrefillApplied.current = true;

        const mappedLines = prefills.map((item) => ({
            partName: item.sku ? `${item.name} (${item.sku})` : item.name,
            spec: '',
            method: '',
            material: '',
            tolerance: '',
            finish: '',
            quantity: item.quantity > 0 ? item.quantity : 1,
            uom: item.uom && item.uom.length > 0 ? item.uom : 'ea',
            targetPrice: undefined,
            requiredDate: item.requiredDate ?? '',
        }));

        if (mappedLines.length > 0) {
            linesFieldArray.replace(mappedLines);
            setPrefillContext({ count: mappedLines.length });
            publishToast({
                variant: 'success',
                title: 'Prefilled from inventory',
                description: `Imported ${mappedLines.length} low-stock item${mappedLines.length === 1 ? '' : 's'} from Inventory alerts.`,
            });
            setStepIndex((index) => (index < 1 ? 1 : index));
        }
    }, [linesFieldArray, setStepIndex]);

    useEffect(() => {
        if (!canCreateRfq) {
            return;
        }

        if (digitalTwinPrefillApplied.current) {
            return;
        }

        const draft = extractDigitalTwinDraft(location.state);
        if (!draft) {
            return;
        }

        digitalTwinPrefillApplied.current = true;

        const currentValues = form.getValues();
        const mappedLines = mapDigitalTwinDraftLines(draft.lines, draft.title);
        const fallbackLines =
            mappedLines.length > 0
                ? mappedLines
                : currentValues.lines.length > 0
                  ? currentValues.lines
                  : createDefaultWizardValues().lines;

        const nextValues: WizardFormValues = {
            ...currentValues,
            title: draft.title ?? currentValues.title,
            summary: draft.summary ?? currentValues.summary,
            notes: draft.notes ?? currentValues.notes,
            lines: fallbackLines,
        };

        form.reset(nextValues);
        replaceLineItems(nextValues.lines);
        setDigitalTwinPrefill(draft);
        setShowDigitalTwinBanner(true);
        setPrefillContext(null);
        setStepIndex((index) => (index < 1 ? 1 : index));

        const nextState = omitDigitalTwinDraftState(location.state);
        if (nextState !== location.state) {
            navigate(location.pathname, { replace: true, state: nextState });
        }
    }, [
        canCreateRfq,
        form,
        replaceLineItems,
        location.pathname,
        location.state,
        navigate,
    ]);

    useEffect(() => {
        if (!canCreateRfq) {
            return;
        }

        if (!titleValue && attachments.length === 0) {
            setRfqAssistSummary(null);
            return;
        }

        const suggestions = buildRfqAssistSuggestions(titleValue, attachments);
        const hasSuggestions = Object.values(suggestions).some(Boolean);

        if (!hasSuggestions) {
            setRfqAssistSummary(null);
            return;
        }

        const signature = buildRfqAssistSignature(titleValue, attachments);
        const allowApply = signature !== rfqAssistSignature.current;
        const { nextLines, applied } = applyRfqAssistToLines(
            lineValues,
            suggestions,
            form.formState.dirtyFields,
            allowApply,
        );

        if (applied) {
            replaceLineItems(nextLines);
            rfqAssistSignature.current = signature;
        }

        const summary = buildRfqAssistSummary(
            suggestions,
            applied ? nextLines : lineValues,
        );
        setRfqAssistSummary(summary);
        setRfqAssistDismissed(false);
    }, [
        canCreateRfq,
        titleValue,
        attachments,
        lineValues,
        form.formState.dirtyFields,
        replaceLineItems,
    ]);

    useEffect(() => {
        if (!canCreateRfq) {
            return;
        }

        const subscription = form.watch((value) => {
            if (typeof window === 'undefined') {
                return;
            }

            try {
                window.localStorage.setItem(
                    LOCAL_STORAGE_KEY,
                    JSON.stringify(value),
                );
            } catch (error) {
                console.warn('Failed to persist RFQ wizard state', error);
            }
        });

        return () => subscription.unsubscribe();
    }, [canCreateRfq, form]);

    if (!canCreateRfq) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>RFQ creation unavailable</title>
                </Helmet>

                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">
                        RFQ creation
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Your current plan does not include RFQ creation. Upgrade
                        to unlock the sourcing workflow.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Upgrade required</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm text-muted-foreground">
                        <p>
                            Visit billing to review plans and enable RFQ
                            creation for your workspace.
                        </p>
                        <Button
                            type="button"
                            onClick={() => navigate('/app/settings/billing')}
                        >
                            View plans
                        </Button>
                    </CardContent>
                </Card>
            </div>
        );
    }

    const currentStep = STEPS[stepIndex];
    const totalSteps = STEPS.length;

    const goNext = async () => {
        const fields = STEP_FIELDS[currentStep.id] ?? [];
        const valid = await form.trigger(fields as (keyof WizardFormValues)[]);
        if (!valid) {
            return;
        }

        setStepIndex((index) => Math.min(index + 1, totalSteps - 1));
    };

    const goBack = () => {
        setStepIndex((index) => Math.max(index - 1, 0));
    };

    const handleFileSelection = (
        event: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const files = event.target.files;
        if (!files || files.length === 0) {
            return;
        }

        if (!canManageAttachments) {
            publishToast({
                variant: 'destructive',
                title: 'Attachments unavailable',
                description:
                    'Upgrade your plan to upload RFQ reference documents.',
            });
            event.target.value = '';
            return;
        }

        const existing = new Map<AttachmentKey, File>(
            attachments.map((file) => [
                `${file.name}-${file.size}-${file.lastModified}` as AttachmentKey,
                file,
            ]),
        );
        const next = [...attachments];

        for (const file of Array.from(files)) {
            const key =
                `${file.name}-${file.size}-${file.lastModified}` as AttachmentKey;
            if (!existing.has(key)) {
                next.push(file);
                existing.set(key, file);
            }
        }

        setAttachments(next);
        event.target.value = '';
    };

    const handleRemoveAttachment = (fileToRemove: File) => {
        setAttachments((current) =>
            current.filter((file) => file !== fileToRemove),
        );
    };

    const handleSupplierSelectedFromDirectory = (supplier: Supplier) => {
        if (!canUseSupplierDirectory) {
            return;
        }

        const current = form.getValues('suppliers') ?? [];
        const identifier = String(supplier.id);

        if (current.some((entry) => entry.id === identifier)) {
            publishToast({
                variant: 'default',
                title: 'Supplier already added',
                description: `${supplier.name} is already in your invite list.`,
            });
            return;
        }

        const selection = buildSupplierSelection(supplier);
        form.setValue('suppliers', [...current, selection], {
            shouldDirty: true,
            shouldTouch: true,
        });
        publishToast({
            variant: 'success',
            title: 'Supplier added',
            description: `${supplier.name} will receive an invitation once the RFQ is created.`,
        });
    };

    const handleRemoveSupplier = (supplierId: string) => {
        const current = form.getValues('suppliers') ?? [];
        const next = current.filter((supplier) => supplier.id !== supplierId);
        form.setValue('suppliers', next, {
            shouldDirty: true,
            shouldTouch: true,
        });
    };

    const onSubmit = form.handleSubmit(async (values) => {
        const dueAt = new Date(values.dueDate);
        if (Number.isNaN(dueAt.getTime())) {
            publishToast({
                variant: 'destructive',
                title: 'Invalid quote submission deadline',
                description:
                    'Enter a valid quote submission deadline before finishing the wizard.',
            });
            return;
        }

        const publishAt = values.publishAt
            ? new Date(values.publishAt)
            : undefined;
        if (publishAt && Number.isNaN(publishAt.getTime())) {
            publishToast({
                variant: 'destructive',
                title: 'Invalid publish date',
                description:
                    'Enter a valid publish date or leave it blank to publish immediately.',
            });
            return;
        }

        const firstLine = values.lines[0];
        const payload: CreateRfqPayload = {
            title: values.title.trim(),
            method: values.method,
            deliveryLocation: values.deliveryLocation.trim(),
            notes: values.summary?.trim() || undefined,
            openBidding: values.openBidding,
            incoterm: values.incoterm?.trim() || undefined,
            paymentTerms: values.paymentTerms?.trim() || undefined,
            taxPercent:
                typeof values.taxPercent === 'number'
                    ? values.taxPercent
                    : undefined,
            currency: values.currency,
            material: firstLine?.material?.trim() || undefined,
            tolerance: firstLine?.tolerance?.trim() || undefined,
            finish: firstLine?.finish?.trim() || undefined,
            dueAt: dueAt.toISOString(),
            digitalTwinId: digitalTwinPrefill?.digital_twin_id,
            items: values.lines.map((line) => ({
                partNumber: line.partName.trim(),
                description: line.spec?.trim() || undefined,
                method: line.method.trim() || undefined,
                material: line.material.trim() || undefined,
                tolerance: line.tolerance?.trim() || undefined,
                finish: line.finish?.trim() || undefined,
                qty: line.quantity,
                uom: line.uom?.trim() || undefined,
                targetPrice:
                    typeof line.targetPrice === 'number'
                        ? line.targetPrice
                        : undefined,
                requiredDate: line.requiredDate || undefined,
            })),
        } satisfies CreateRfqPayload;

        setIsFinalizing(true);

        try {
            const response = await createRfqMutation.mutateAsync(payload);
            const rfqId = response.data.id;

            const supplierIds = (values.suppliers ?? []).map(
                (supplier) => supplier.id,
            );
            if (supplierIds.length > 0) {
                await inviteSuppliersMutation.mutateAsync({
                    rfqId,
                    supplierIds,
                });
            }

            if (attachments.length > 0 && canManageAttachments) {
                for (const file of attachments) {
                    await uploadAttachmentMutation.mutateAsync({
                        rfqId,
                        file,
                    });
                }
            }

            if (values.publishNow) {
                if (!canPublishRfq) {
                    publishToast({
                        variant: 'destructive',
                        title: 'Publishing unavailable',
                        description:
                            'Upgrade your plan to publish RFQs automatically.',
                    });
                } else {
                    await publishRfqMutation.mutateAsync({
                        rfqId,
                        dueAt,
                        publishAt: publishAt ?? undefined,
                        notifySuppliers: true,
                    });
                }
            }

            publishToast({
                variant: 'success',
                title:
                    values.publishNow && canPublishRfq
                        ? 'RFQ published'
                        : 'RFQ created',
                description:
                    values.publishNow && canPublishRfq
                        ? 'Suppliers can now review and respond to your RFQ.'
                        : 'Your RFQ draft is ready. You can continue editing details in the RFQ workspace.',
            });

            if (typeof window !== 'undefined') {
                window.localStorage.removeItem(LOCAL_STORAGE_KEY);
            }

            setAttachments([]);
            form.reset(createDefaultWizardValues());
            setStepIndex(0);

            navigate(`/app/rfqs/${rfqId}`);
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to create RFQ.';
            publishToast({
                variant: 'destructive',
                title: 'Creation failed',
                description: message,
            });
        } finally {
            setIsFinalizing(false);
        }
    });

    return (
        <>
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Create RFQ</title>
                </Helmet>

                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">
                        RFQ creation wizard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Capture sourcing requirements, invite suppliers, and
                        prepare your RFQ for publication.
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-card px-4 py-3 text-sm">
                    <span>
                        Step {stepIndex + 1} of {totalSteps}:{' '}
                        <strong>{currentStep.title}</strong>
                    </span>
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        {STEPS.map((step, index) => (
                            <span
                                key={step.id}
                                className={
                                    index === stepIndex
                                        ? 'font-semibold text-foreground'
                                        : ''
                                }
                            >
                                {index + 1}. {step.title}
                            </span>
                        ))}
                    </div>
                </div>

                <DocumentNumberPreview docType="rfq" className="max-w-md" />

                {digitalTwinPrefill && showDigitalTwinBanner ? (
                    <Alert>
                        <AlertTitle>Digital twin linked</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <span>
                                {digitalTwinPrefill.title ??
                                    'The selected digital twin'}{' '}
                                prefilled the RFQ basics,{' '}
                                {digitalTwinPrefill.lines.length} line
                                {digitalTwinPrefill.lines.length === 1
                                    ? ''
                                    : 's'}
                                , and {digitalTwinPrefill.specs.length} spec
                                entr
                                {digitalTwinPrefill.specs.length === 1
                                    ? 'y'
                                    : 'ies'}
                                .
                            </span>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        navigate(
                                            `/app/library/digital-twins/${digitalTwinPrefill.digital_twin_id}`,
                                        )
                                    }
                                >
                                    View digital twin
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setShowDigitalTwinBanner(false)
                                    }
                                >
                                    Dismiss
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                ) : null}

                {rfqAssistSummary && !rfqAssistDismissed ? (
                    <Alert>
                        <AlertTitle>RFQ assist suggestions</AlertTitle>
                        <AlertDescription className="flex flex-col gap-4">
                            <div className="grid gap-2 text-sm">
                                <p className="text-muted-foreground">
                                    Suggestions are based on the title and
                                    attachment names. Review before publishing.
                                </p>
                                {rfqAssistSummary.suggestions.length > 0 ? (
                                    <ul className="list-disc space-y-1 pl-5">
                                        {rfqAssistSummary.suggestions.map(
                                            (suggestion, index) => (
                                                <li
                                                    key={`${suggestion.field}-${index}`}
                                                >
                                                    <span className="font-semibold">
                                                        {suggestion.field}:
                                                    </span>{' '}
                                                    {suggestion.value}{' '}
                                                    <span className="text-xs text-muted-foreground">
                                                        ({suggestion.note})
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                ) : null}
                            </div>
                            {rfqAssistSummary.conflicts.length > 0 ? (
                                <div className="rounded-md border border-amber-200/30 bg-amber-50/5 p-3 text-xs text-amber-100">
                                    <p className="font-semibold tracking-wide uppercase">
                                        Potential conflicts
                                    </p>
                                    <ul className="mt-2 list-disc space-y-1 pl-4">
                                        {rfqAssistSummary.conflicts.map(
                                            (conflict, index) => (
                                                <li
                                                    key={`${conflict}-${index}`}
                                                >
                                                    {conflict}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            ) : null}
                            {rfqAssistSummary.missing.length > 0 ? (
                                <div className="rounded-md border border-slate-200/20 bg-slate-950/40 p-3 text-xs text-slate-200">
                                    <p className="font-semibold tracking-wide uppercase">
                                        Missing details
                                    </p>
                                    <ul className="mt-2 list-disc space-y-1 pl-4">
                                        {rfqAssistSummary.missing.map(
                                            (item, index) => (
                                                <li key={`${item}-${index}`}>
                                                    {item}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            ) : null}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setRfqAssistDismissed(true)}
                                >
                                    Dismiss
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <form
                    className="flex flex-1 flex-col gap-6"
                    onSubmit={onSubmit}
                >
                    {currentStep.id === 'basics' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>RFQ basics</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        placeholder="Machined bracket RFQ"
                                        {...form.register('title')}
                                    />
                                    {form.formState.errors.title ? (
                                        <p className="text-sm text-destructive">
                                            {
                                                form.formState.errors.title
                                                    .message
                                            }
                                        </p>
                                    ) : null}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="summary">Summary</Label>
                                    <Textarea
                                        id="summary"
                                        rows={3}
                                        placeholder="Provide context for suppliers: use case, quality expectations, inspection requirements."
                                        {...form.register('summary')}
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="method">
                                            Manufacturing method
                                        </Label>
                                        <Select
                                            value={form.watch('method')}
                                            onValueChange={(value: RfqMethod) =>
                                                form.setValue('method', value, {
                                                    shouldDirty: true,
                                                    shouldTouch: true,
                                                })
                                            }
                                        >
                                            <SelectTrigger id="method">
                                                <SelectValue placeholder="Select a manufacturing method" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {RFQ_METHOD_OPTIONS.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option.value}
                                                            value={option.value}
                                                        >
                                                            <div className="flex w-full flex-col gap-0.5">
                                                                <span className="text-sm font-medium text-foreground">
                                                                    {
                                                                        option.label
                                                                    }
                                                                </span>
                                                                {/* <span className="text-xs text-muted-foreground">{option.description}</span> */}
                                                            </div>
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="deliveryLocation">
                                            Delivery location
                                        </Label>
                                        <Input
                                            id="deliveryLocation"
                                            placeholder="Elements Supply · Austin, TX"
                                            {...form.register(
                                                'deliveryLocation',
                                            )}
                                        />
                                        {form.formState.errors
                                            .deliveryLocation ? (
                                            <p className="text-sm text-destructive">
                                                {
                                                    form.formState.errors
                                                        .deliveryLocation
                                                        .message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="openBidding"
                                        {...form.register('openBidding')}
                                    />
                                    <Label htmlFor="openBidding">
                                        Enable open bidding
                                    </Label>
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    {currentStep.id === 'lines' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Line items</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {prefillContext ? (
                                    <Alert>
                                        <AlertTitle>
                                            Imported from Inventory alerts
                                        </AlertTitle>
                                        <AlertDescription className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                            <span>
                                                {prefillContext.count} low-stock
                                                item
                                                {prefillContext.count === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                were added automatically. Review
                                                and adjust quantities before
                                                publishing.
                                            </span>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setPrefillContext(null)
                                                }
                                            >
                                                Dismiss
                                            </Button>
                                        </AlertDescription>
                                    </Alert>
                                ) : null}

                                {digitalTwinPrefill &&
                                digitalTwinPrefill.specs.length > 0 ? (
                                    <div className="rounded-lg border border-dashed bg-muted/40 p-4 text-sm">
                                        <p className="font-semibold text-foreground">
                                            Specs from the digital twin
                                        </p>
                                        <ul className="mt-2 grid gap-2 text-xs text-muted-foreground md:grid-cols-2">
                                            {digitalTwinPrefill.specs
                                                .slice(0, 8)
                                                .map((spec) => (
                                                    <li key={spec.id}>
                                                        <span className="font-medium text-foreground">
                                                            {spec.name}
                                                        </span>
                                                        :{' '}
                                                        {formatDigitalTwinSpecValue(
                                                            spec.value,
                                                            spec.uom,
                                                        )}
                                                    </li>
                                                ))}
                                        </ul>
                                        {digitalTwinPrefill.specs.length > 8 ? (
                                            <p className="mt-2 text-xs text-muted-foreground">
                                                Showing the first 8
                                                specifications. View the digital
                                                twin for the complete list.
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}

                                {linesFieldArray.fields.map((field, index) => (
                                    <WizardLineFields
                                        key={field.id}
                                        index={index}
                                        form={form}
                                        onRemove={() =>
                                            linesFieldArray.remove(index)
                                        }
                                        canRemove={
                                            linesFieldArray.fields.length > 1
                                        }
                                    />
                                ))}

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        linesFieldArray.append({
                                            partName: '',
                                            spec: '',
                                            method: '',
                                            material: '',
                                            tolerance: '',
                                            finish: '',
                                            quantity: 1,
                                            uom: 'ea',
                                            targetPrice: undefined,
                                            requiredDate: '',
                                        })
                                    }
                                >
                                    Add another line
                                </Button>
                            </CardContent>
                        </Card>
                    ) : null}

                    {currentStep.id === 'suppliers' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Suppliers</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <div>
                                            <Label>Suppliers</Label>
                                            <p className="text-xs text-muted-foreground">
                                                {selectedSuppliers.length === 0
                                                    ? 'No suppliers selected yet.'
                                                    : `${selectedSuppliers.length} supplier${selectedSuppliers.length === 1 ? '' : 's'} selected.`}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                canUseSupplierDirectory
                                                    ? setSupplierPickerOpen(
                                                          true,
                                                      )
                                                    : null
                                            }
                                            disabled={!canUseSupplierDirectory}
                                        >
                                            Browse directory
                                        </Button>
                                    </div>

                                    {canUseSupplierDirectory ? (
                                        <p className="text-xs text-muted-foreground">
                                            Search your approved supplier
                                            directory and add participants.
                                            Remove them below as needed.
                                        </p>
                                    ) : (
                                        <p className="text-xs text-destructive">
                                            {canInviteSuppliers
                                                ? 'Directory browsing requires an upgraded plan.'
                                                : 'Upgrade your plan to invite suppliers to RFQs.'}
                                        </p>
                                    )}

                                    {selectedSuppliers.length === 0 ? (
                                        <div className="rounded-md border border-dashed border-border p-4 text-sm text-muted-foreground">
                                            Suppliers can only be added through
                                            the directory. Use the button above
                                            to select participants.
                                        </div>
                                    ) : (
                                        <ul className="space-y-2">
                                            {selectedSuppliers.map(
                                                (supplier) => (
                                                    <li
                                                        key={supplier.id}
                                                        className="flex items-center justify-between rounded-md border p-3 text-sm"
                                                    >
                                                        <div>
                                                            <p className="font-medium text-foreground">
                                                                {supplier.name}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {supplier.location ??
                                                                    'Location unavailable'}
                                                            </p>
                                                            {supplier.methods &&
                                                            supplier.methods
                                                                .length > 0 ? (
                                                                <p className="text-xs text-muted-foreground">
                                                                    Methods:{' '}
                                                                    {supplier.methods.join(
                                                                        ', ',
                                                                    )}
                                                                </p>
                                                            ) : null}
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleRemoveSupplier(
                                                                    supplier.id,
                                                                )
                                                            }
                                                        >
                                                            Remove
                                                        </Button>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    {currentStep.id === 'terms' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Commercial terms</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="publishAt">
                                            Publish date
                                        </Label>
                                        <Input
                                            id="publishAt"
                                            type="datetime-local"
                                            {...form.register('publishAt')}
                                            disabled={!canPublishRfq}
                                        />
                                        {form.formState.errors.publishAt ? (
                                            <p className="text-sm text-destructive">
                                                {
                                                    form.formState.errors
                                                        .publishAt.message
                                                }
                                            </p>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                {canPublishRfq
                                                    ? 'Leave blank to publish immediately after creating the RFQ.'
                                                    : 'Plan upgrade required to schedule automatic publication.'}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="dueDate">
                                            Quote submission deadline
                                        </Label>
                                        <Input
                                            id="dueDate"
                                            type="datetime-local"
                                            {...form.register('dueDate')}
                                        />
                                        {form.formState.errors.dueDate ? (
                                            <p className="text-sm text-destructive">
                                                {
                                                    form.formState.errors
                                                        .dueDate.message
                                                }
                                            </p>
                                        ) : null}
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="incoterm">
                                        Incoterm (optional)
                                    </Label>
                                    <Input
                                        id="incoterm"
                                        placeholder="FOB Shenzhen"
                                        {...form.register('incoterm')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="paymentTerms">
                                        Payment terms (optional)
                                    </Label>
                                    <Input
                                        id="paymentTerms"
                                        placeholder="Net 30"
                                        {...form.register('paymentTerms')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="taxPercent">
                                        Estimated tax rate (optional)
                                    </Label>
                                    <Input
                                        id="taxPercent"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        placeholder="0"
                                        {...form.register('taxPercent')}
                                    />
                                    {form.formState.errors.taxPercent ? (
                                        <p className="text-sm text-destructive">
                                            {
                                                form.formState.errors.taxPercent
                                                    .message
                                            }
                                        </p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Capture expected tax percentage for
                                            quote comparisons.
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="currency">
                                        RFQ currency
                                    </Label>
                                    <Select
                                        value={currencyValue}
                                        onValueChange={(next) =>
                                            form.setValue('currency', next, {
                                                shouldDirty: true,
                                                shouldTouch: true,
                                            })
                                        }
                                    >
                                        <SelectTrigger id="currency">
                                            <SelectValue placeholder="Select currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencyOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.formState.errors.currency ? (
                                        <p className="text-sm text-destructive">
                                            {
                                                form.formState.errors.currency
                                                    .message
                                            }
                                        </p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Suppliers and downstream documents
                                            inherit this currency for pricing,
                                            so select carefully.
                                        </p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    {currentStep.id === 'attachments' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Attachments</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <label
                                    className={`flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground${
                                        canManageAttachments
                                            ? 'cursor-pointer'
                                            : 'cursor-not-allowed opacity-60'
                                    }`}
                                    aria-disabled={!canManageAttachments}
                                >
                                    <Input
                                        type="file"
                                        multiple
                                        className="hidden"
                                        onChange={handleFileSelection}
                                        disabled={!canManageAttachments}
                                    />
                                    <span>
                                        Drag & drop or click to add reference
                                        documents.
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Attach CAD, specification sheets, or
                                        inspection templates.
                                    </span>
                                    {!canManageAttachments ? (
                                        <span className="text-xs text-destructive">
                                            Attachments require an upgraded
                                            plan.
                                        </span>
                                    ) : null}
                                </label>

                                {digitalTwinPrefill &&
                                digitalTwinPrefill.attachments.length > 0 ? (
                                    <div className="rounded-lg border border-dashed bg-muted/40 p-4 text-sm">
                                        <p className="font-semibold text-foreground">
                                            Linked digital twin files
                                        </p>
                                        <ul className="mt-2 space-y-1 text-xs text-muted-foreground">
                                            {digitalTwinPrefill.attachments.map(
                                                (asset) => (
                                                    <li
                                                        key={asset.id}
                                                        className="flex flex-wrap items-center justify-between gap-2"
                                                    >
                                                        <span className="text-foreground">
                                                            {asset.filename}{' '}
                                                            <span className="text-muted-foreground">
                                                                (
                                                                {asset.type ??
                                                                    'file'}{' '}
                                                                ·{' '}
                                                                {formatFileSize(
                                                                    asset.size_bytes,
                                                                )}
                                                                )
                                                            </span>
                                                        </span>
                                                        {asset.download_url ? (
                                                            <a
                                                                href={
                                                                    asset.download_url
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-xs font-medium text-primary underline-offset-4 hover:underline"
                                                            >
                                                                Download
                                                            </a>
                                                        ) : null}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            These files stay in the Digital Twin
                                            Library. Upload additional RFQ-only
                                            references below if needed.
                                        </p>
                                    </div>
                                ) : null}

                                {attachments.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No attachments selected yet.
                                    </p>
                                ) : (
                                    <ul className="space-y-2 text-sm">
                                        {attachments.map((file, index) => (
                                            <li
                                                key={`${file.name}-${index}`}
                                                className="flex items-center justify-between gap-2 rounded-md border p-2"
                                            >
                                                <span>
                                                    {file.name} (
                                                    {Math.round(
                                                        file.size / 1024,
                                                    )}{' '}
                                                    KB)
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleRemoveAttachment(
                                                            file,
                                                        )
                                                    }
                                                >
                                                    Remove
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    ) : null}

                    {currentStep.id === 'review' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Review & publish</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {digitalTwinPrefill ? (
                                    <div className="rounded-lg border border-dashed bg-muted/40 p-4 text-sm">
                                        <p className="font-semibold text-foreground">
                                            Digital twin reference
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Linked twin #
                                            {digitalTwinPrefill.digital_twin_id}{' '}
                                            contributed{' '}
                                            {digitalTwinPrefill.specs.length}{' '}
                                            specs and{' '}
                                            {
                                                digitalTwinPrefill.attachments
                                                    .length
                                            }{' '}
                                            asset
                                            {digitalTwinPrefill.attachments
                                                .length === 1
                                                ? ''
                                                : 's'}
                                            . Changes here do not modify the
                                            source digital twin.
                                        </p>
                                    </div>
                                ) : null}

                                <div className="grid gap-1 text-sm">
                                    <p className="font-semibold text-foreground">
                                        {reviewSnapshot.title}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {reviewSnapshot.summary}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Method:{' '}
                                        {getRfqMethodLabel(
                                            reviewSnapshot.method,
                                        )}{' '}
                                        •{' '}
                                        {reviewSnapshot.openBidding
                                            ? 'Open bidding enabled'
                                            : 'Private invitations'}{' '}
                                        • {reviewSnapshot.lines?.length ?? 0}{' '}
                                        line
                                        {(reviewSnapshot.lines?.length ?? 0) ===
                                        1
                                            ? ''
                                            : 's'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Currency:{' '}
                                        {reviewSnapshot.currency ?? '—'} • Tax:{' '}
                                        {typeof reviewSnapshot.taxPercent ===
                                        'number'
                                            ? `${reviewSnapshot.taxPercent}%`
                                            : '—'}{' '}
                                        • Publish at:{' '}
                                        {formatDateLabel(
                                            reviewSnapshot.publishAt,
                                            true,
                                        )}{' '}
                                        • Due:{' '}
                                        {formatDateLabel(
                                            reviewSnapshot.dueDate,
                                            true,
                                        )}
                                    </p>
                                </div>

                                <div className="rounded-lg border p-4">
                                    <p className="text-sm font-semibold text-foreground">
                                        Lines
                                    </p>
                                    <ul className="mt-2 space-y-2 text-sm text-muted-foreground">
                                        {(reviewSnapshot.lines ?? []).map(
                                            (line, index) => (
                                                <li
                                                    key={`${line.partName}-${index}`}
                                                    className="rounded-md border border-border p-3"
                                                >
                                                    <p className="font-medium text-foreground">
                                                        {index + 1}.{' '}
                                                        {line.partName}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {line.quantity}{' '}
                                                        {line.uom} • Required by{' '}
                                                        {formatDateLabel(
                                                            line.requiredDate,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Method: {line.method} •
                                                        Material:{' '}
                                                        {line.material}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {line.tolerance
                                                            ? `Tolerance: ${line.tolerance}`
                                                            : 'Tolerance: —'}{' '}
                                                        •{' '}
                                                        {line.finish
                                                            ? `Finish: ${line.finish}`
                                                            : 'Finish: —'}
                                                    </p>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>

                                <div className="rounded-lg border p-4">
                                    <p className="text-sm font-semibold text-foreground">
                                        Suppliers
                                    </p>
                                    {reviewSnapshot.suppliers &&
                                    reviewSnapshot.suppliers.length > 0 ? (
                                        <ul className="mt-2 space-y-2 text-sm text-muted-foreground">
                                            {reviewSnapshot.suppliers.map(
                                                (supplier, index) => (
                                                    <li
                                                        key={`${supplier.id}-${index}`}
                                                        className="flex items-center justify-between"
                                                    >
                                                        <div>
                                                            <p className="font-medium text-foreground">
                                                                {supplier.name}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {supplier.location ??
                                                                    'Location unavailable'}
                                                            </p>
                                                        </div>
                                                        {supplier.methods &&
                                                        supplier.methods
                                                            .length > 0 ? (
                                                            <p className="text-xs text-muted-foreground">
                                                                Methods:{' '}
                                                                {supplier.methods.join(
                                                                    ', ',
                                                                )}
                                                            </p>
                                                        ) : null}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    ) : (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            No suppliers selected.
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="publishNow"
                                        checked={reviewSnapshot.publishNow}
                                        onCheckedChange={(value) =>
                                            form.setValue(
                                                'publishNow',
                                                Boolean(value),
                                            )
                                        }
                                        disabled={!canPublishRfq}
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="publishNow">
                                            Publish immediately after creation
                                        </Label>
                                        {!canPublishRfq ? (
                                            <span className="text-xs text-muted-foreground">
                                                Upgrade plan to publish RFQs
                                                automatically.
                                            </span>
                                        ) : null}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ) : null}

                    <div className="flex items-center justify-between border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={goBack}
                            disabled={stepIndex === 0}
                        >
                            Back
                        </Button>
                        {currentStep.id === 'review' ? (
                            <Button type="submit" disabled={isSubmitting}>
                                {isSubmitting
                                    ? 'Finishing...'
                                    : 'Finish & create RFQ'}
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={goNext}
                                disabled={isSubmitting}
                            >
                                Next
                            </Button>
                        )}
                    </div>
                </form>
            </div>
            {canUseSupplierDirectory ? (
                <SupplierDirectoryPicker
                    open={isSupplierPickerOpen}
                    onOpenChange={setSupplierPickerOpen}
                    onSelect={handleSupplierSelectedFromDirectory}
                />
            ) : null}
        </>
    );
}
