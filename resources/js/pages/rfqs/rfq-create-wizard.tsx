import { useEffect, useMemo, useRef, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';
import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useFieldArray, useForm, useWatch, type UseFormReturn } from 'react-hook-form';
import { z } from 'zod';

import { DocumentNumberPreview } from '@/components/documents/document-number-preview';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useCreateRfq, useInviteSuppliers, usePublishRfq, useUploadAttachment } from '@/hooks/api/rfqs';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
import { useUoms } from '@/hooks/api/use-uoms';
import { useBaseUomQuantity } from '@/hooks/use-uom-conversion-helper';
import { RfqTypeEnum, type CreateRfqRequestItemsInner } from '@/sdk';
import type { Supplier } from '@/types/sourcing';
import { consumeLowStockRfqPrefill } from '@/lib/low-stock-rfq-prefill';

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
        .union([z.coerce.number({ invalid_type_error: 'Target price must be numeric.' }), z.literal('')])
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
    type: z.nativeEnum(RfqTypeEnum),
    clientCompany: z.string().min(1, 'Client company is required.'),
    openBidding: z.boolean().default(false),
    notes: z.string().optional(),
    lines: z.array(lineSchema).min(1, 'Add at least one line item.'),
    suppliers: z.array(supplierSelectionSchema).default([]),
    publishNow: z.boolean().default(false),
    incoterm: z.string().optional(),
    paymentTerms: z.string().optional(),
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
        .min(1, 'Due date is required.')
        .refine((value) => {
            const parsed = new Date(value);
            if (Number.isNaN(parsed.getTime())) {
                return false;
            }

            return parsed > new Date();
        }, 'Due date must be in the future.'),
})
    .superRefine((values, ctx) => {
        if (!values.dueDate) {
            return;
        }

        const dueDate = new Date(values.dueDate);
        if (Number.isNaN(dueDate.getTime())) {
            ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['dueDate'], message: 'Enter a valid due date.' });
            return;
        }

        if (values.publishAt && values.publishAt.length > 0) {
            const publishAt = new Date(values.publishAt);
            if (Number.isNaN(publishAt.getTime())) {
                ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['publishAt'], message: 'Publish date is invalid.' });
            } else {
                if (publishAt > dueDate) {
                    ctx.addIssue({
                        code: z.ZodIssueCode.custom,
                        path: ['publishAt'],
                        message: 'Publish date must be before the due date.',
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
    basics: ['title', 'summary', 'type', 'clientCompany', 'notes', 'openBidding'],
    lines: ['lines'],
    suppliers: ['suppliers'],
    terms: ['publishAt', 'dueDate', 'incoterm', 'paymentTerms'],
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

function normalizeStoredSupplier(selection: Partial<WizardSupplier> | undefined): WizardSupplier | null {
    if (!selection || !selection.id) {
        return null;
    }

    const methods = Array.isArray(selection.methods)
        ? selection.methods.filter((method): method is string => typeof method === 'string' && method.length > 0).slice(0, 3)
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
    const methods = supplier.capabilities.methods && supplier.capabilities.methods.length > 0
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

function WizardLineFields({ index, form, onRemove, canRemove }: WizardLineFieldsProps) {
    const { register, formState, control } = form;
    const quantity = useWatch({ control, name: `lines.${index}.quantity` });
    const uom = useWatch({ control, name: `lines.${index}.uom` });
    const { baseUomLabel, convertedLabel, isEnabled, isLoading } = useBaseUomQuantity(quantity, uom);
    const uomQuery = useUoms({ enabled: isEnabled });
    const uomOptions = useMemo(() => {
        const data = uomQuery.data ?? [];
        return data.map((item) => {
            const shorthand = item.symbol && item.symbol.length > 0 ? item.symbol.toUpperCase() : item.code.toUpperCase();
            return {
                ...item,
                shorthand,
                description: `${item.name}${item.siBase ? ' - Base unit' : ''}`,
            };
        });
    }, [uomQuery.data]);
    const showUomSelect = isEnabled && !uomQuery.isError && (uomQuery.isLoading || uomOptions.length > 0);
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
                <Label htmlFor={`lines.${index}.partName`}>Part / description</Label>
                <Input
                    id={`lines.${index}.partName`}
                    placeholder={`Line ${index + 1} description`}
                    {...register(`lines.${index}.partName`)}
                />
                {lineErrors?.partName ? (
                    <p className="text-sm text-destructive">{lineErrors.partName.message}</p>
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
                    <Label htmlFor={`lines.${index}.method`}>Manufacturing method</Label>
                    <Input
                        id={`lines.${index}.method`}
                        placeholder="CNC machining"
                        {...register(`lines.${index}.method`)}
                        aria-invalid={lineErrors?.method ? 'true' : undefined}
                    />
                    {lineErrors?.method ? (
                        <p className="text-sm text-destructive">{lineErrors.method.message}</p>
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
                        <p className="text-sm text-destructive">{lineErrors.material.message}</p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.tolerance`}>Tolerance (optional)</Label>
                    <Input
                        id={`lines.${index}.tolerance`}
                        placeholder="±0.05 mm"
                        {...register(`lines.${index}.tolerance`)}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.finish`}>Finish (optional)</Label>
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
                        <p className="text-sm text-destructive">{lineErrors.quantity.message}</p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.uom`}>UoM</Label>
                    {showUomSelect ? (
                        <Controller
                            control={control}
                            name={`lines.${index}.uom`}
                            render={({ field }) => {
                                const value = typeof field.value === 'string' ? field.value : '';
                                const normalizedValue = value.toLowerCase();
                                const options = value && !uomOptions.some((option) => option.code.toLowerCase() === normalizedValue)
                                    ? [
                                          ...uomOptions,
                                          {
                                              code: value,
                                              shorthand: value.toUpperCase(),
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
                                            aria-invalid={lineErrors?.uom ? 'true' : undefined}
                                        >
                                            <SelectValue placeholder={uomQuery.isLoading ? 'Loading units...' : 'Select unit'} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.map((option) => (
                                                <SelectItem key={option.code} value={option.code}>
                                                    <div className="flex w-full flex-col gap-0.5">
                                                        <span className="text-sm font-medium text-foreground">{option.shorthand}</span>
                                                        <span className="text-xs text-muted-foreground">{option.description}</span>
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
                        <p className="text-sm text-destructive">{lineErrors.uom.message}</p>
                    ) : null}
                    {isEnabled && uomQuery.isError ? (
                        <p className="text-xs text-muted-foreground">
                            Unable to load the unit catalog right now. Enter a custom unit code if needed.
                        </p>
                    ) : null}
                    {conversionMessage ? (
                        <p className="text-xs text-muted-foreground">{conversionMessage}</p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.requiredDate`}>Required date</Label>
                    <Input
                        id={`lines.${index}.requiredDate`}
                        type="date"
                        {...register(`lines.${index}.requiredDate`)}
                    />
                    {lineErrors?.requiredDate ? (
                        <p className="text-sm text-destructive">{lineErrors.requiredDate.message}</p>
                    ) : null}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`lines.${index}.targetPrice`}>Target price (optional)</Label>
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
                    <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
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
        type: RfqTypeEnum.Manufacture,
        clientCompany: '',
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
        publishAt: '',
        dueDate: '',
    } satisfies WizardFormValues;
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

    return includeTime ? dateTimeFormatter.format(parsed) : dateFormatter.format(parsed);
}

export function RfqCreateWizard() {
    const [stepIndex, setStepIndex] = useState(0);
    const [attachments, setAttachments] = useState<File[]>([]);
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [isSupplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const { hasFeature, state: authState } = useAuth();
    const createRfqMutation = useCreateRfq();
    const inviteSuppliersMutation = useInviteSuppliers();
    const publishRfqMutation = usePublishRfq();
    const uploadAttachmentMutation = useUploadAttachment();
    const navigate = useNavigate();

    const featureFlagsLoaded = Object.keys(authState.featureFlags ?? {}).length > 0;
    const allowFeature = (key: string) => (featureFlagsLoaded ? hasFeature(key) : true);

    const canCreateRfq = allowFeature('rfqs.create');
    const canPublishRfq = allowFeature('rfqs.publish');
    const canInviteSuppliers = allowFeature('rfqs.suppliers.invite');
    const canManageAttachments = allowFeature('rfqs.attachments.manage');
    const canBrowseSupplierDirectory = allowFeature('suppliers.directory.browse');
    const canUseSupplierDirectory = canInviteSuppliers && canBrowseSupplierDirectory;

    const form = useForm<WizardFormValues>({
        resolver: zodResolver(wizardSchema),
        defaultValues: createDefaultWizardValues(),
        mode: 'onChange',
    });

    const { control } = form;
    const selectedSuppliers = (useWatch({ control, name: 'suppliers' }) ?? []) as WizardSupplier[];
    const linesFieldArray = useFieldArray({ control, name: 'lines' });
    const [prefillContext, setPrefillContext] = useState<{ count: number } | null>(null);
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

            const stored = JSON.parse(raw) as Partial<WizardFormValues> & { supplierInputs?: string };
            const restoredLines =
                stored.lines && stored.lines.length > 0
                    ? stored.lines.map((line) => ({
                          ...line,
                          requiredDate: line.requiredDate ?? '',
                      }))
                    : form.getValues().lines;

            const normalizedSuppliers: WizardSupplier[] = Array.isArray(stored.suppliers)
                ? stored.suppliers
                      .map((entry) => normalizeStoredSupplier(entry))
                      .filter((entry): entry is WizardSupplier => Boolean(entry))
                : [];

            if (normalizedSuppliers.length === 0 && typeof stored.supplierInputs === 'string') {
                const legacyIds = parseLegacySupplierList(stored.supplierInputs);
                normalizedSuppliers.push(
                    ...legacyIds.map((id) => ({
                        id,
                        name: id,
                    })),
                );
            }

            form.reset({
                ...form.getValues(),
                ...stored,
                lines: restoredLines,
                suppliers: normalizedSuppliers,
                publishAt: stored.publishAt ?? form.getValues().publishAt,
                dueDate: stored.dueDate ?? form.getValues().dueDate,
            });
        } catch (error) {
            console.warn('Failed to restore RFQ wizard draft from storage', error);
        }
    }, [canCreateRfq, form]);

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

        const subscription = form.watch((value) => {
            if (typeof window === 'undefined') {
                return;
            }

            try {
                window.localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(value));
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
                    <h1 className="text-2xl font-semibold text-foreground">RFQ creation</h1>
                    <p className="text-sm text-muted-foreground">
                        Your current plan does not include RFQ creation. Upgrade to unlock the sourcing workflow.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Upgrade required</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm text-muted-foreground">
                        <p>Visit billing to review plans and enable RFQ creation for your workspace.</p>
                        <Button type="button" onClick={() => navigate('/app/settings?tab=billing')}>
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

    const handleFileSelection = (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = event.target.files;
        if (!files || files.length === 0) {
            return;
        }

        if (!canManageAttachments) {
            publishToast({
                variant: 'destructive',
                title: 'Attachments unavailable',
                description: 'Upgrade your plan to upload RFQ reference documents.',
            });
            event.target.value = '';
            return;
        }

        const existing = new Map<AttachmentKey, File>(
            attachments.map((file) => [`${file.name}-${file.size}-${file.lastModified}` as AttachmentKey, file]),
        );
        const next = [...attachments];

        for (const file of Array.from(files)) {
            const key = `${file.name}-${file.size}-${file.lastModified}` as AttachmentKey;
            if (!existing.has(key)) {
                next.push(file);
                existing.set(key, file);
            }
        }

        setAttachments(next);
        event.target.value = '';
    };

    const handleRemoveAttachment = (fileToRemove: File) => {
        setAttachments((current) => current.filter((file) => file !== fileToRemove));
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
        form.setValue('suppliers', [...current, selection], { shouldDirty: true, shouldTouch: true });
        publishToast({
            variant: 'success',
            title: 'Supplier added',
            description: `${supplier.name} will receive an invitation once the RFQ is created.`,
        });
    };

    const handleRemoveSupplier = (supplierId: string) => {
        const current = form.getValues('suppliers') ?? [];
        const next = current.filter((supplier) => supplier.id !== supplierId);
        form.setValue('suppliers', next, { shouldDirty: true, shouldTouch: true });
    };

    const onSubmit = form.handleSubmit(async (values) => {
        const dueAt = new Date(values.dueDate);
        if (Number.isNaN(dueAt.getTime())) {
            publishToast({
                variant: 'destructive',
                title: 'Invalid due date',
                description: 'Enter a valid due date before finishing the wizard.',
            });
            return;
        }

        const publishAt = values.publishAt ? new Date(values.publishAt) : undefined;
        if (publishAt && Number.isNaN(publishAt.getTime())) {
            publishToast({
                variant: 'destructive',
                title: 'Invalid publish date',
                description: 'Enter a valid publish date or leave it blank to publish immediately.',
            });
            return;
        }

        const payload = {
            itemName: values.title,
            type: values.type,
            clientCompany: values.clientCompany,
            status: 'awaiting' as const,
            items: values.lines.map<CreateRfqRequestItemsInner>((line) => ({
                partName: line.partName,
                spec: line.spec,
                method: line.method,
                material: line.material,
                tolerance: line.tolerance || undefined,
                finish: line.finish || undefined,
                quantity: line.quantity,
                uom: line.uom,
                targetPrice: line.targetPrice,
                requiredDate: line.requiredDate,
            })),
            deadlineAt: dueAt,
            isOpenBidding: values.openBidding,
            notes: values.summary || undefined,
            // TODO: include CAD upload once storage pipeline is ready.
        } satisfies Parameters<typeof createRfqMutation.mutateAsync>[0];

        setIsFinalizing(true);

        try {
            const response = await createRfqMutation.mutateAsync(payload);
            const rfqId = response.data.id;

            const supplierIds = (values.suppliers ?? []).map((supplier) => supplier.id);
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
                        description: 'Upgrade your plan to publish RFQs automatically.',
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
                title: values.publishNow && canPublishRfq ? 'RFQ published' : 'RFQ created',
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
            const message = error instanceof Error ? error.message : 'Unable to create RFQ.';
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
                <h1 className="text-2xl font-semibold text-foreground">RFQ creation wizard</h1>
                <p className="text-sm text-muted-foreground">
                    Capture sourcing requirements, invite suppliers, and prepare your RFQ for publication.
                </p>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-card px-4 py-3 text-sm">
                <span>
                    Step {stepIndex + 1} of {totalSteps}: <strong>{currentStep.title}</strong>
                </span>
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    {STEPS.map((step, index) => (
                        <span key={step.id} className={index === stepIndex ? 'font-semibold text-foreground' : ''}>
                            {index + 1}. {step.title}
                        </span>
                    ))}
                </div>
            </div>

            <DocumentNumberPreview docType="rfq" className="max-w-md" />

            <form className="flex flex-1 flex-col gap-6" onSubmit={onSubmit}>
                {currentStep.id === 'basics' ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>RFQ basics</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input id="title" placeholder="Machined bracket RFQ" {...form.register('title')} />
                                {form.formState.errors.title ? (
                                    <p className="text-sm text-destructive">{form.formState.errors.title.message}</p>
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
                                    <Label htmlFor="type">RFQ type</Label>
                                    <select
                                        id="type"
                                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        {...form.register('type')}
                                    >
                                        <option value="manufacture">Manufacture</option>
                                        <option value="ready_made">Ready made</option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="clientCompany">Client company</Label>
                                    <Input id="clientCompany" placeholder="Elements Supply" {...form.register('clientCompany')} />
                                    {form.formState.errors.clientCompany ? (
                                        <p className="text-sm text-destructive">{form.formState.errors.clientCompany.message}</p>
                                    ) : null}
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox id="openBidding" {...form.register('openBidding')} />
                                <Label htmlFor="openBidding">Enable open bidding</Label>
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
                                    <AlertTitle>Imported from Inventory alerts</AlertTitle>
                                    <AlertDescription className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                        <span>
                                            {prefillContext.count} low-stock item{prefillContext.count === 1 ? '' : 's'} were added automatically. Review and adjust quantities before publishing.
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setPrefillContext(null)}
                                        >
                                            Dismiss
                                        </Button>
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            {linesFieldArray.fields.map((field, index) => (
                                <WizardLineFields
                                    key={field.id}
                                    index={index}
                                    form={form}
                                    onRemove={() => linesFieldArray.remove(index)}
                                    canRemove={linesFieldArray.fields.length > 1}
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
                                        onClick={() => (canUseSupplierDirectory ? setSupplierPickerOpen(true) : null)}
                                        disabled={!canUseSupplierDirectory}
                                    >
                                        Browse directory
                                    </Button>
                                </div>

                                {canUseSupplierDirectory ? (
                                    <p className="text-xs text-muted-foreground">
                                        Search your approved supplier directory and add participants. Remove them below as needed.
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
                                        Suppliers can only be added through the directory. Use the button above to select participants.
                                    </div>
                                ) : (
                                    <ul className="space-y-2">
                                        {selectedSuppliers.map((supplier) => (
                                            <li
                                                key={supplier.id}
                                                className="flex items-center justify-between rounded-md border p-3 text-sm"
                                            >
                                                <div>
                                                    <p className="font-medium text-foreground">{supplier.name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {supplier.location ?? 'Location unavailable'}
                                                    </p>
                                                    {supplier.methods && supplier.methods.length > 0 ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            Methods: {supplier.methods.join(', ')}
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleRemoveSupplier(supplier.id)}
                                                >
                                                    Remove
                                                </Button>
                                            </li>
                                        ))}
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
                                    <Label htmlFor="publishAt">Publish date</Label>
                                    <Input
                                        id="publishAt"
                                        type="datetime-local"
                                        {...form.register('publishAt')}
                                        disabled={!canPublishRfq}
                                    />
                                    {form.formState.errors.publishAt ? (
                                        <p className="text-sm text-destructive">{form.formState.errors.publishAt.message}</p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            {canPublishRfq
                                                ? 'Leave blank to publish immediately after creating the RFQ.'
                                                : 'Plan upgrade required to schedule automatic publication.'}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="dueDate">Due date</Label>
                                    <Input id="dueDate" type="datetime-local" {...form.register('dueDate')} />
                                    {form.formState.errors.dueDate ? (
                                        <p className="text-sm text-destructive">{form.formState.errors.dueDate.message}</p>
                                    ) : null}
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="incoterm">Incoterm (optional)</Label>
                                <Input id="incoterm" placeholder="FOB Shenzhen" {...form.register('incoterm')} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="paymentTerms">Payment terms (optional)</Label>
                                <Input id="paymentTerms" placeholder="Net 30" {...form.register('paymentTerms')} />
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
                                    canManageAttachments ? ' cursor-pointer' : ' cursor-not-allowed opacity-60'
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
                                <span>Drag & drop or click to add reference documents.</span>
                                <span className="text-xs text-muted-foreground">
                                    Attach CAD, specification sheets, or inspection templates.
                                </span>
                                {!canManageAttachments ? (
                                    <span className="text-xs text-destructive">
                                        Attachments require an upgraded plan.
                                    </span>
                                ) : null}
                            </label>

                            {attachments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No attachments selected yet.</p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {attachments.map((file, index) => (
                                        <li key={`${file.name}-${index}`} className="flex items-center justify-between gap-2 rounded-md border p-2">
                                            <span>
                                                {file.name} ({Math.round(file.size / 1024)} KB)
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleRemoveAttachment(file)}
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
                            <div className="grid gap-1 text-sm">
                                <p className="font-semibold text-foreground">{reviewSnapshot.title}</p>
                                <p className="text-muted-foreground">{reviewSnapshot.summary}</p>
                                <p className="text-xs text-muted-foreground">
                                    Type: {reviewSnapshot.type === RfqTypeEnum.Manufacture ? 'Manufacture' : 'Ready made'} •{' '}
                                    {reviewSnapshot.openBidding ? 'Open bidding enabled' : 'Private invitations'} •{' '}
                                    {(reviewSnapshot.lines?.length ?? 0)} line{(reviewSnapshot.lines?.length ?? 0) === 1 ? '' : 's'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Publish at: {formatDateLabel(reviewSnapshot.publishAt, true)} • Due: {formatDateLabel(reviewSnapshot.dueDate, true)}
                                </p>
                            </div>

                            <div className="rounded-lg border p-4">
                                <p className="text-sm font-semibold text-foreground">Lines</p>
                                <ul className="mt-2 space-y-2 text-sm text-muted-foreground">
                                    {(reviewSnapshot.lines ?? []).map((line, index) => (
                                        <li key={`${line.partName}-${index}`} className="rounded-md border border-border p-3">
                                            <p className="font-medium text-foreground">
                                                {index + 1}. {line.partName}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {line.quantity} {line.uom} • Required by {formatDateLabel(line.requiredDate)}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Method: {line.method} • Material: {line.material}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {line.tolerance ? `Tolerance: ${line.tolerance}` : 'Tolerance: —'} •{' '}
                                                {line.finish ? `Finish: ${line.finish}` : 'Finish: —'}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div className="rounded-lg border p-4">
                                <p className="text-sm font-semibold text-foreground">Suppliers</p>
                                {reviewSnapshot.suppliers && reviewSnapshot.suppliers.length > 0 ? (
                                    <ul className="mt-2 space-y-2 text-sm text-muted-foreground">
                                        {reviewSnapshot.suppliers.map((supplier, index) => (
                                            <li key={`${supplier.id}-${index}`} className="flex items-center justify-between">
                                                <div>
                                                    <p className="font-medium text-foreground">{supplier.name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {supplier.location ?? 'Location unavailable'}
                                                    </p>
                                                </div>
                                                {supplier.methods && supplier.methods.length > 0 ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        Methods: {supplier.methods.join(', ')}
                                                    </p>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-muted-foreground">No suppliers selected.</p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="publishNow"
                                    checked={reviewSnapshot.publishNow}
                                    onCheckedChange={(value) => form.setValue('publishNow', Boolean(value))}
                                    disabled={!canPublishRfq}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="publishNow">Publish immediately after creation</Label>
                                    {!canPublishRfq ? (
                                        <span className="text-xs text-muted-foreground">Upgrade plan to publish RFQs automatically.</span>
                                    ) : null}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <div className="flex items-center justify-between border-t pt-4">
                    <Button type="button" variant="outline" onClick={goBack} disabled={stepIndex === 0}>
                        Back
                    </Button>
                    {currentStep.id === 'review' ? (
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? 'Finishing...' : 'Finish & create RFQ'}
                        </Button>
                    ) : (
                        <Button type="button" onClick={goNext} disabled={isSubmitting}>
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
