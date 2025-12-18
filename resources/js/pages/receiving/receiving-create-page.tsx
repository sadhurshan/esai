import { useEffect, useMemo, useRef, useState } from 'react';
import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { PackageSearch, PackagePlus, Send } from 'lucide-react';

import { DocumentNumberPreview } from '@/components/documents/document-number-preview';
import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { GrnLineEditor, type GrnLineFormValue } from '@/components/receiving/grn-line-editor';
import { MoneyCell } from '@/components/quotes/money-cell';
import { PoStatusBadge } from '@/components/pos/po-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useCreateGrn } from '@/hooks/api/receiving/use-create-grn';
import { usePurchaseOrder } from '@/hooks/api/usePurchaseOrder';
import { usePurchaseOrders } from '@/hooks/api/usePurchaseOrders';
import type { PurchaseOrderDetail, PurchaseOrderLine, PurchaseOrderSummary } from '@/types/sourcing';

const MAX_REFERENCE_LEN = 60;
const MAX_NOTE_LEN = 500;
const MAX_LINE_NOTE_LEN = 280;

const grnLineSchema = z
    .object({
        poLineId: z.number().int(),
        lineNo: z.number().int().nullable().optional(),
        description: z.string().nullable().optional(),
        orderedQty: z.number().nonnegative(),
        previouslyReceived: z.number().nullable().optional(),
        remainingQty: z.number().nullable().optional(),
        qtyReceived: z
            .number({ invalid_type_error: 'Enter a number' })
            .min(0, 'Quantity must be zero or greater')
            .default(0),
        uom: z.string().nullable().optional(),
        notes: z
            .string()
            .max(MAX_LINE_NOTE_LEN, `Notes cannot exceed ${MAX_LINE_NOTE_LEN} characters`)
            .nullable()
            .optional(),
    })
    .superRefine((line, ctx) => {
        if (line.remainingQty === null || line.remainingQty === undefined) {
            return;
        }
        if (line.qtyReceived > line.remainingQty + 1e-6) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: 'Cannot receive more than the remaining quantity',
                path: ['qtyReceived'],
            });
        }
    });

const grnFormSchema = z.object({
    purchaseOrderId: z.number().int().positive('Select a purchase order'),
    receivedAt: z
        .string()
        .min(1, 'Received date is required')
        .refine((value) => !Number.isNaN(Date.parse(value)), 'Provide a valid date'),
    reference: z
        .string()
        .max(MAX_REFERENCE_LEN, `Reference cannot exceed ${MAX_REFERENCE_LEN} characters`)
        .optional()
        .or(z.literal('')),
    notes: z
        .string()
        .max(MAX_NOTE_LEN, `Notes cannot exceed ${MAX_NOTE_LEN} characters`)
        .optional()
        .or(z.literal('')),
    lines: z.array(grnLineSchema),
});

export type GrnFormValues = z.infer<typeof grnFormSchema>;

export function ReceivingCreatePage() {
    const navigate = useNavigate();
    const { hasFeature, state, activePersona } = useAuth();
    const createGrn = useCreateGrn();
    const [poPickerOpen, setPoPickerOpen] = useState(false);
    const [selectedPoSummary, setSelectedPoSummary] = useState<PurchaseOrderSummary | null>(null);
    const lastPoAppliedRef = useRef<number | null>(null);

    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const supplierRole = state.user?.role === 'supplier';
    const isSupplierPersona = activePersona?.type === 'supplier' || supplierRole;
    const receivingEnabled = hasFeature('inventory_enabled');

    const today = useMemo(() => new Date().toISOString().slice(0, 10), []);

    const form = useForm<GrnFormValues>({
        resolver: zodResolver(grnFormSchema),
        defaultValues: {
            purchaseOrderId: 0,
            receivedAt: today,
            reference: '',
            notes: '',
            lines: [],
        },
    });

    const {
        handleSubmit,
        setValue,
        formState,
        resetField,
        clearErrors,
        setError,
    } = form;
    const [activePoId, setActivePoId] = useState(() => form.getValues('purchaseOrderId'));

    const rootLinesError =
        formState.errors.lines && !Array.isArray(formState.errors.lines) && formState.errors.lines.message
            ? formState.errors.lines.message
            : null;

    const effectivePoId = Number.isFinite(activePoId) && !isSupplierPersona ? activePoId : 0;
    const poDetailQuery = usePurchaseOrder(effectivePoId);
    const poDetail = poDetailQuery.data;

    useEffect(() => {
        if (!poDetail) {
            return;
        }

        if (lastPoAppliedRef.current === poDetail.id) {
            return;
        }

        const mappedLines = buildLineDefaults(poDetail);
        setValue('lines', mappedLines, { shouldDirty: true, shouldTouch: true });
        lastPoAppliedRef.current = poDetail.id;
        clearErrors('lines');
    }, [poDetail, setValue, clearErrors]);

    const handlePoSelect = (po: PurchaseOrderSummary) => {
        setSelectedPoSummary(po);
        setValue('purchaseOrderId', po.id, { shouldDirty: true, shouldTouch: true });
        setValue('lines', [], { shouldDirty: true, shouldTouch: true });
        clearErrors('lines');
        setPoPickerOpen(false);
        lastPoAppliedRef.current = null;
        setActivePoId(po.id);
    };

    const handlePoClear = () => {
        setSelectedPoSummary(null);
        setValue('purchaseOrderId', 0, { shouldDirty: true, shouldTouch: true });
        setValue('lines', [], { shouldDirty: true, shouldTouch: true });
        lastPoAppliedRef.current = null;
        resetField('lines');
        setActivePoId(0);
    };

    const submitGrn = async (values: GrnFormValues, status: 'draft' | 'posted') => {
        clearErrors('lines');
        if (!Array.isArray(values.lines) || values.lines.length === 0) {
            setError('lines', {
                type: 'manual',
                message: 'This purchase order has no open quantities left to receive. Choose another PO to continue.',
            });
            return;
        }

        if (!values.lines.some((line) => line.qtyReceived > 0)) {
            setError('lines', {
                type: 'manual',
                message: 'Enter at least one received quantity greater than zero before saving.',
            });
            return;
        }

        const response = await createGrn.mutateAsync({
            purchaseOrderId: values.purchaseOrderId,
            receivedAt: values.receivedAt,
            reference: values.reference?.trim() ? values.reference.trim() : undefined,
            notes: values.notes?.trim() ? values.notes.trim() : undefined,
            status,
            lines: values.lines
                .filter((line) => line.qtyReceived > 0)
                .map((line) => ({
                    poLineId: line.poLineId,
                    quantityReceived: line.qtyReceived,
                    uom: line.uom ?? undefined,
                    notes: line.notes?.trim() ? line.notes.trim() : undefined,
                })),
        });

        if (response?.id) {
            navigate(`/app/receiving/${response.id}`);
        } else {
            navigate('/app/receiving');
        }
    };

    const handleSaveDraft = handleSubmit((values) => submitGrn(values, 'draft'));
    const handlePostGrn = handleSubmit((values) => submitGrn(values, 'posted'));

    const isSubmitting = createGrn.isPending;
    const mutationError = createGrn.error;

    if (featureFlagsLoaded && isSupplierPersona) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Record receiving</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <EmptyState
                    title="Receiving is buyer-only"
                    description="Suppliers cannot post GRNs. Switch to a buyer persona to record goods receipt notes or quality findings."
                    icon={<PackagePlus className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    if (featureFlagsLoaded && !receivingEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Record receiving</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Receiving unavailable"
                    description="Upgrade your Elements Supply plan to record goods receipt notes."
                    icon={<PackagePlus className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    const activePo = selectedPoSummary ?? (poDetail as PurchaseOrderSummary | undefined) ?? null;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Record receipt</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Operations</p>
                    <h1 className="text-2xl font-semibold text-foreground">Record goods receipt</h1>
                    <p className="text-sm text-muted-foreground">
                        Select a purchase order, capture arriving quantities, and keep receiving reconciled.
                    </p>
                </div>
                <Button type="button" variant="secondary" onClick={() => navigate('/app/receiving')}>
                    Cancel
                </Button>
            </div>

            <DocumentNumberPreview docType="grn" className="max-w-md" />

            {mutationError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to save GRN</AlertTitle>
                    <AlertDescription>{mutationError.message ?? 'Please try again.'}</AlertDescription>
                </Alert>
            ) : null}

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Purchase order</CardTitle>
                    <CardDescription>GRNs must reference an existing PO to maintain traceability.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {activePo ? (
                        <div className="flex flex-wrap items-center gap-3">
                            <Badge variant="outline">PO #{activePo.poNumber}</Badge>
                            <PoStatusBadge status={activePo.status} />
                            {activePo.revisionNo ? (
                                <span className="text-xs uppercase tracking-wide text-muted-foreground">
                                    Rev {activePo.revisionNo}
                                </span>
                            ) : null}
                            <Button type="button" variant="outline" size="sm" onClick={() => setPoPickerOpen(true)}>
                                Change PO
                            </Button>
                            <Button type="button" variant="ghost" size="sm" onClick={handlePoClear}>
                                Clear
                            </Button>
                        </div>
                    ) : (
                        <div className="rounded-md border border-dashed border-border/70 bg-muted/30 p-4 text-sm text-muted-foreground">
                            <p className="font-medium text-foreground">No purchase order selected</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Choose a PO to load open lines and available quantities.
                            </p>
                            <Button type="button" className="mt-3" onClick={() => setPoPickerOpen(true)}>
                                <PackageSearch className="mr-2 h-4 w-4" /> Browse purchase orders
                            </Button>
                        </div>
                    )}

                    {activePo ? (
                        <div className="grid gap-4 md:grid-cols-3">
                            <MoneyCell amountMinor={activePo.subtotalMinor ?? activePo.totalMinor} currency={activePo.currency} label="Subtotal" />
                            <MoneyCell amountMinor={activePo.taxAmountMinor} currency={activePo.currency} label="Tax" />
                            <MoneyCell amountMinor={activePo.totalMinor} currency={activePo.currency} label="Total" />
                        </div>
                    ) : null}
                </CardContent>
            </Card>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Receipt details</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="received-at">Received date</Label>
                        <Input id="received-at" type="date" disabled={isSubmitting} {...form.register('receivedAt')} />
                        {formState.errors.receivedAt ? (
                            <p className="text-xs text-destructive">{formState.errors.receivedAt.message}</p>
                        ) : null}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="reference">Packing slip / reference</Label>
                        <Input id="reference" type="text" placeholder="e.g. ASN12345" disabled={isSubmitting} {...form.register('reference')} />
                        {formState.errors.reference ? (
                            <p className="text-xs text-destructive">{formState.errors.reference.message}</p>
                        ) : null}
                    </div>
                    <div className="space-y-2 md:col-span-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea id="notes" rows={4} placeholder="Optional receiving notes" disabled={isSubmitting} {...form.register('notes')} />
                        {formState.errors.notes ? (
                            <p className="text-xs text-destructive">{formState.errors.notes.message}</p>
                        ) : null}
                        <p className="text-xs text-muted-foreground">
                            Capture damages, shortages, or carrier issues. Attachments are uploaded after saving the draft.
                        </p>
                    </div>
                </CardContent>
            </Card>

            {activePoId > 0 ? (
                poDetailQuery.isLoading ? (
                    <Skeleton className="h-64 w-full rounded-lg" />
                ) : (
                    <GrnLineEditor form={form} disabled={isSubmitting} />
                )
            ) : (
                <EmptyState
                    title="Select a PO to continue"
                    description="We will load every open PO line so you can capture what arrived."
                    icon={<PackageSearch className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="Choose purchase order"
                    ctaProps={{ onClick: () => setPoPickerOpen(true) }}
                />
            )}

            {rootLinesError ? <p className="text-sm text-destructive">{rootLinesError}</p> : null}

            <div className="sticky bottom-0 flex flex-col gap-3 rounded-lg border border-border/70 bg-card/95 p-4 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="text-sm text-muted-foreground">
                        Posting will update PO received quantities and unlock matching.
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" disabled={isSubmitting} onClick={handleSaveDraft}>
                            Save draft
                        </Button>
                        <Button type="button" disabled={isSubmitting} onClick={handlePostGrn}>
                            <Send className="mr-2 h-4 w-4" /> Post GRN
                        </Button>
                    </div>
                </div>
            </div>

            <PurchaseOrderPickerDialog open={poPickerOpen} onOpenChange={setPoPickerOpen} onSelect={handlePoSelect} />
        </div>
    );
}

function buildLineDefaults(po: PurchaseOrderDetail): GrnLineFormValue[] {
    return (po.lines ?? [])
        .map((line) => mapPoLine(line))
        .filter((line) => {
            if (line.remainingQty === undefined || line.remainingQty === null) {
                return true;
            }
            return line.remainingQty > 0;
        });
}

function mapPoLine(line: PurchaseOrderLine): GrnLineFormValue {
    const ordered = line.quantity ?? 0;
    const previously = line.receivedQuantity ?? 0;
    const remaining = line.remainingQuantity ?? Math.max(ordered - previously, 0);

    return {
        id: line.id,
        poLineId: line.id,
        lineNo: line.lineNo,
        description: line.description,
        orderedQty: ordered,
        previouslyReceived: previously,
        remainingQty: remaining,
        qtyReceived: 0,
        uom: line.uom,
        notes: '',
    };
}

interface PurchaseOrderPickerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (po: PurchaseOrderSummary) => void;
}

function useDebouncedValue<T>(value: T, delay = 300) {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const handle = window.setTimeout(() => setDebounced(value), delay);
        return () => window.clearTimeout(handle);
    }, [value, delay]);

    return debounced;
}

function PurchaseOrderPickerDialog({ open, onOpenChange, onSelect }: PurchaseOrderPickerDialogProps) {
    const { formatDate, formatMoney } = useFormatting();
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebouncedValue(search);

    const poQuery = usePurchaseOrders(
        useMemo(
            () => ({
                per_page: 8,
                q: debouncedSearch.trim() ? debouncedSearch.trim() : undefined,
            }),
            [debouncedSearch],
        ),
    );

    const items = poQuery.data?.items ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Select purchase order</DialogTitle>
                    <DialogDescription>Search approved purchase orders to base your goods receipt on.</DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="po-search">Search</Label>
                        <Input
                            id="po-search"
                            placeholder="Supplier, PO #, keyword"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    {poQuery.isLoading ? (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner className="h-4 w-4" /> Loading purchase orders…
                        </div>
                    ) : null}

                    {!poQuery.isLoading && items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No purchase orders matched your search.</p>
                    ) : null}

                    <div className="grid gap-3">
                        {items.map((po) => {
                            const totalDisplay = po.currency
                                ? `${formatMoney((po.totalMinor ?? 0) / 100, { currency: po.currency })} total`
                                : 'Total unavailable';

                            return (
                                <div key={po.id} className="rounded-md border border-border/60 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-semibold text-foreground">PO #{po.poNumber}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {po.supplierName ?? 'Unnamed supplier'} • {formatDate(po.createdAt)}
                                            </p>
                                        </div>
                                        <PoStatusBadge status={po.status} />
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                                        <div className="text-sm text-muted-foreground">{totalDisplay}</div>
                                        <Button type="button" size="sm" onClick={() => onSelect(po)}>
                                            Use PO
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
