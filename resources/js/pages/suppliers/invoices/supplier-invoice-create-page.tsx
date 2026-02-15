import { BadgeDollarSign, Loader2, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { EmptyState } from '@/components/empty-state';
import { FileDropzone } from '@/components/file-dropzone';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useSubmitSupplierInvoice } from '@/hooks/api/invoices/use-submit-supplier-invoice';
import {
    useSupplierCreateInvoice,
    type SupplierInvoiceLineInput,
} from '@/hooks/api/invoices/use-supplier-create-invoice';
import { usePo } from '@/hooks/api/pos/use-po';
import { useTaxCodes } from '@/hooks/api/use-tax-codes';
import { cn } from '@/lib/utils';
import type { PurchaseOrderDetail } from '@/types/sourcing';

export interface InvoiceLineFormState {
    poLineId: number;
    invoiceLineId?: number;
    lineNo?: number;
    description: string;
    quantity: number;
    unitPrice: number;
    uom?: string;
    taxCodeIds: number[];
    include: boolean;
    remainingQuantity?: number | null;
}

const MINOR_FACTOR = 100;

export function SupplierInvoiceCreatePage() {
    const navigate = useNavigate();
    const { hasFeature, state, activePersona } = useAuth();
    const { formatMoney, formatNumber } = useFormatting();
    const [searchParams] = useSearchParams();
    const initialPoId = Number(searchParams.get('po') ?? NaN);
    const [poIdInput, setPoIdInput] = useState(() =>
        Number.isFinite(initialPoId) ? String(initialPoId) : '',
    );
    const [selectedPoId, setSelectedPoId] = useState<number | null>(
        Number.isFinite(initialPoId) ? initialPoId : null,
    );

    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const supplierRole = state.user?.role === 'supplier';
    const isSupplierPersona = activePersona?.type === 'supplier';
    const supplierPortalEligible =
        supplierRole ||
        isSupplierPersona ||
        hasFeature('supplier_portal_enabled');
    const supplierInvoicingEnabled =
        supplierPortalEligible && hasFeature('supplier_invoicing_enabled');
    const canAccessBillingFeatures = ['owner', 'buyer_admin'].includes(
        state.user?.role ?? '',
    );
    const allowTaxCodePicker =
        supplierInvoicingEnabled &&
        canAccessBillingFeatures &&
        !isSupplierPersona;

    const poQuery = usePo(selectedPoId ?? 0);
    const purchaseOrder = selectedPoId ? (poQuery.data ?? null) : null;

    const [invoiceNumber, setInvoiceNumber] = useState('');
    const [invoiceDate, setInvoiceDate] = useState(() =>
        new Date().toISOString().slice(0, 10),
    );
    const [dueDate, setDueDate] = useState('');
    const [currency, setCurrency] = useState('');
    const [lineItemOverrides, setLineItemOverrides] = useState<
        Record<number, Partial<InvoiceLineFormState>>
    >({});
    const [documentFile, setDocumentFile] = useState<File | null>(null);
    const [submissionNote, setSubmissionNote] = useState('');
    const resolvedCurrency = purchaseOrder?.currency || currency || 'USD';

    const formatCurrencyValue = (
        minorValue?: number | null,
        currencyCode?: string | null,
    ) =>
        formatMoney((minorValue ?? 0) / MINOR_FACTOR, {
            currency: currencyCode ?? resolvedCurrency ?? 'USD',
        });

    const createMutation = useSupplierCreateInvoice();
    const submitMutation = useSubmitSupplierInvoice();
    const isSaving = createMutation.isPending || submitMutation.isPending;

    const taxCodesQuery = useTaxCodes(undefined, {
        enabled: allowTaxCodePicker,
    });
    const taxCodeOptions = useMemo(() => {
        if (!allowTaxCodePicker) {
            return [];
        }
        return taxCodesQuery.items.map((tax) => ({
            id: tax.id,
            label: `${tax.code ?? 'Tax'} · ${tax.name ?? 'Unnamed'}`,
            detail:
                typeof tax.ratePercent === 'number'
                    ? `${formatNumber(tax.ratePercent)}%`
                    : null,
        }));
    }, [allowTaxCodePicker, formatNumber, taxCodesQuery.items]);

    const baseLineItems = useMemo<InvoiceLineFormState[]>(() => {
        if (!purchaseOrder) {
            return [];
        }

        return purchaseOrder.lines.map((line) => ({
            poLineId: line.id,
            invoiceLineId: undefined,
            lineNo: line.lineNo,
            description: line.description,
            quantity: Math.max(
                1,
                Math.ceil(line.remainingQuantity ?? line.quantity ?? 1),
            ),
            unitPrice: line.unitPrice ?? 0,
            uom: line.uom,
            taxCodeIds: [],
            include: true,
            remainingQuantity:
                line.remainingQuantity ??
                Math.max(
                    0,
                    (line.quantity ?? 0) - (line.invoicedQuantity ?? 0),
                ),
        }));
    }, [purchaseOrder]);

    const lineItems = useMemo(
        () =>
            baseLineItems.map((line) => ({
                ...line,
                ...(lineItemOverrides[line.poLineId] ?? {}),
            })),
        [baseLineItems, lineItemOverrides],
    );

    const selectedLines = useMemo(
        () =>
            lineItems.filter(
                (line) =>
                    line.include && line.quantity > 0 && line.unitPrice > 0,
            ),
        [lineItems],
    );

    const totals = useMemo(() => {
        const subtotal = selectedLines.reduce(
            (sum, line) => sum + line.quantity * line.unitPrice,
            0,
        );
        return {
            subtotal,
            subtotalMinor: Math.round(subtotal * MINOR_FACTOR),
        };
    }, [selectedLines]);

    const readyForDraft = Boolean(
        purchaseOrder &&
        invoiceNumber.trim().length > 0 &&
        invoiceDate &&
        selectedLines.length > 0,
    );
    const readyForSubmit = readyForDraft;

    const handleLoadPurchaseOrder = () => {
        const parsed = Number(poIdInput);
        if (!Number.isFinite(parsed) || parsed <= 0) {
            return;
        }
        setSelectedPoId(parsed);
        setInvoiceNumber('');
        setInvoiceDate(new Date().toISOString().slice(0, 10));
        setDueDate('');
        setCurrency('');
        setLineItemOverrides({});
        setDocumentFile(null);
        setSubmissionNote('');
    };

    const updateLine = (
        poLineId: number,
        patch: Partial<InvoiceLineFormState>,
    ) => {
        setLineItemOverrides((current) => ({
            ...current,
            [poLineId]: {
                ...(current[poLineId] ?? {}),
                ...patch,
            },
        }));
    };

    const handleFileDrop = (files: File[]) => {
        setDocumentFile(files[0] ?? null);
    };

    const handlePersist = (mode: 'draft' | 'submit') => {
        if (!purchaseOrder || selectedLines.length === 0) {
            return;
        }

        const payload: SupplierInvoiceLineInput[] = selectedLines.map(
            (line) => ({
                poLineId: line.poLineId,
                quantity: Math.max(1, Math.floor(line.quantity)),
                unitPrice: Number(line.unitPrice.toFixed(4)),
                description: line.description,
                uom: line.uom,
                taxCodeIds: line.taxCodeIds,
            }),
        );

        createMutation.mutate(
            {
                purchaseOrderId: purchaseOrder.id,
                invoiceNumber: invoiceNumber.trim(),
                invoiceDate,
                dueDate: dueDate || undefined,
                currency: resolvedCurrency,
                document: documentFile ?? undefined,
                lines: payload,
            },
            {
                onSuccess: (invoice) => {
                    if (mode === 'submit') {
                        submitMutation.mutate(
                            {
                                invoiceId: invoice.id,
                                note: submissionNote.trim() || undefined,
                            },
                            {
                                onSuccess: (submitted) => {
                                    navigate(
                                        `/app/supplier/invoices/${submitted.id}`,
                                    );
                                },
                            },
                        );
                        return;
                    }
                    navigate(`/app/supplier/invoices/${invoice.id}`);
                },
            },
        );
    };

    if (featureFlagsLoaded && !supplierInvoicingEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Supplier portal unavailable"
                    description="This workspace plan does not include supplier-authored invoicing. Request an upgrade to enable it."
                    icon={<Wallet className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Create supplier invoice</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Supplier portal
                    </p>
                    <h1 className="text-3xl font-semibold text-foreground">
                        Create invoice
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Build an invoice from a purchase order, add taxes, and
                        submit it for buyer review.
                    </p>
                </div>
                <Button variant="outline" size="sm" asChild>
                    <Link to="/app/supplier/orders">View purchase orders</Link>
                </Button>
            </div>

            <Card className="border-border/70">
                <CardHeader>
                    <CardTitle>Select purchase order</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-[2fr,auto]">
                    <div className="space-y-2">
                        <Label htmlFor="po-id">Purchase order ID</Label>
                        <div className="flex gap-2">
                            <Input
                                id="po-id"
                                value={poIdInput}
                                onChange={(event) =>
                                    setPoIdInput(event.target.value)
                                }
                                placeholder="Enter PO numeric ID"
                                inputMode="numeric"
                                disabled={isSaving}
                            />
                            <Button
                                type="button"
                                onClick={handleLoadPurchaseOrder}
                                disabled={isSaving}
                            >
                                Load
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Open a PO from the supplier dashboard, or paste the
                            PO ID shared by your buyer.
                        </p>
                    </div>
                    {purchaseOrder ? (
                        <div className="border-success/30 bg-success/5 rounded-lg border p-4 text-sm">
                            <p className="text-success font-semibold">
                                Loaded {purchaseOrder.poNumber}
                            </p>
                            <p className="text-muted-foreground">
                                {purchaseOrder.supplierName}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {purchaseOrder.lines.length} lines ·{' '}
                                {formatCurrencyValue(
                                    purchaseOrder.totalMinor,
                                    purchaseOrder.currency,
                                )}
                            </p>
                        </div>
                    ) : null}
                </CardContent>
            </Card>

            {selectedPoId ? (
                <PurchaseOrderStateCard
                    purchaseOrder={purchaseOrder}
                    poQueryStatus={{
                        isLoading: poQuery.isLoading,
                        isError: poQuery.isError,
                        refetch: poQuery.refetch,
                    }}
                    formatCurrency={(value, currencyCode) =>
                        formatCurrencyValue(value, currencyCode)
                    }
                />
            ) : null}

            {purchaseOrder ? (
                <>
                    <InvoiceHeaderCard
                        invoiceNumber={invoiceNumber}
                        onInvoiceNumberChange={setInvoiceNumber}
                        invoiceDate={invoiceDate}
                        onInvoiceDateChange={setInvoiceDate}
                        dueDate={dueDate}
                        onDueDateChange={setDueDate}
                        currency={resolvedCurrency}
                        onCurrencyChange={setCurrency}
                        disabled={isSaving}
                        poCurrency={purchaseOrder.currency}
                    />

                    <InvoiceLinesCard
                        lineItems={lineItems}
                        onLineChange={updateLine}
                        currency={resolvedCurrency}
                        formatNumber={formatNumber}
                        taxCodes={taxCodeOptions}
                        allowTaxCodes={allowTaxCodePicker}
                        disabled={isSaving}
                    />

                    <AttachmentsCard
                        documentFile={documentFile}
                        onFileChange={handleFileDrop}
                        disabled={isSaving}
                    />

                    <SubmissionCard
                        totals={totals}
                        onSaveDraft={() => handlePersist('draft')}
                        onSubmit={() => handlePersist('submit')}
                        submissionNote={submissionNote}
                        onSubmissionNoteChange={setSubmissionNote}
                        disabledDraft={!readyForDraft || isSaving}
                        disabledSubmit={!readyForSubmit || isSaving}
                        isSaving={isSaving}
                    />
                </>
            ) : null}
        </div>
    );
}

export interface PurchaseOrderStateCardProps {
    purchaseOrder: PurchaseOrderDetail | null;
    poQueryStatus: {
        isLoading: boolean;
        isError: boolean;
        refetch: () => unknown;
    };
    formatCurrency: (value?: number | null, currency?: string | null) => string;
}

export function PurchaseOrderStateCard({
    purchaseOrder,
    poQueryStatus,
    formatCurrency,
}: PurchaseOrderStateCardProps) {
    if (poQueryStatus.isLoading) {
        return (
            <Card className="border-border/70">
                <CardContent className="flex items-center gap-3 py-6 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" /> Loading
                    purchase order details…
                </CardContent>
            </Card>
        );
    }

    if (poQueryStatus.isError || !purchaseOrder) {
        return (
            <Card className="border-destructive/30 bg-destructive/5">
                <CardContent className="flex items-center justify-between gap-3 py-4">
                    <div>
                        <p className="font-semibold text-destructive">
                            Unable to load purchase order.
                        </p>
                        <p className="text-sm text-destructive/80">
                            Ensure the PO exists and that you have access.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => poQueryStatus.refetch()}
                    >
                        Retry
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className="border-border/70">
            <CardHeader>
                <CardTitle>Purchase order summary</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 md:grid-cols-3">
                <SummaryItem label="PO number" value={purchaseOrder.poNumber} />
                <SummaryItem label="Status" value={purchaseOrder.status} />
                <SummaryItem label="Currency" value={purchaseOrder.currency} />
                <SummaryItem
                    label="Lines"
                    value={`${purchaseOrder.lines.length} items`}
                />
                <SummaryItem
                    label="Total value"
                    value={formatCurrency(
                        purchaseOrder.totalMinor,
                        purchaseOrder.currency,
                    )}
                />
                <SummaryItem
                    label="Revision"
                    value={`Rev ${purchaseOrder.revisionNo}`}
                />
            </CardContent>
        </Card>
    );
}

interface InvoiceHeaderCardProps {
    invoiceNumber: string;
    onInvoiceNumberChange: (value: string) => void;
    invoiceDate: string;
    onInvoiceDateChange: (value: string) => void;
    dueDate: string;
    onDueDateChange: (value: string) => void;
    currency: string;
    onCurrencyChange: (value: string) => void;
    disabled?: boolean;
    poCurrency?: string;
    currencyReadOnly?: boolean;
}

export function InvoiceHeaderCard({
    invoiceNumber,
    onInvoiceNumberChange,
    invoiceDate,
    onInvoiceDateChange,
    dueDate,
    onDueDateChange,
    currency,
    onCurrencyChange,
    disabled,
    poCurrency,
    currencyReadOnly,
}: InvoiceHeaderCardProps) {
    return (
        <Card className="border-border/70">
            <CardHeader>
                <CardTitle>Invoice details</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 md:grid-cols-4">
                <Field label="Invoice number">
                    <Input
                        value={invoiceNumber}
                        onChange={(event) =>
                            onInvoiceNumberChange(event.target.value)
                        }
                        placeholder="e.g. INV-1043"
                        disabled={disabled}
                    />
                </Field>
                <Field label="Invoice date">
                    <Input
                        type="date"
                        value={invoiceDate}
                        onChange={(event) =>
                            onInvoiceDateChange(event.target.value)
                        }
                        disabled={disabled}
                    />
                </Field>
                <Field label="Due date">
                    <Input
                        type="date"
                        value={dueDate}
                        onChange={(event) =>
                            onDueDateChange(event.target.value)
                        }
                        disabled={disabled}
                    />
                </Field>
                <Field label="Currency">
                    <Input
                        value={currency}
                        onChange={(event) =>
                            onCurrencyChange(event.target.value.toUpperCase())
                        }
                        maxLength={3}
                        disabled={disabled || currencyReadOnly}
                    />
                    {poCurrency && poCurrency !== currency ? (
                        <p className="text-xs text-amber-600">
                            PO currency is {poCurrency}; confirm if different.
                        </p>
                    ) : null}
                </Field>
            </CardContent>
        </Card>
    );
}

interface InvoiceLinesCardProps {
    lineItems: InvoiceLineFormState[];
    onLineChange: (
        poLineId: number,
        patch: Partial<InvoiceLineFormState>,
    ) => void;
    currency: string;
    formatNumber: (
        value?: number,
        options?: Intl.NumberFormatOptions,
    ) => string;
    taxCodes: Array<{
        id?: number | null;
        label: string;
        detail: string | null;
    }>;
    allowTaxCodes: boolean;
    disabled?: boolean;
    mode?: 'create' | 'edit';
}

export function InvoiceLinesCard({
    lineItems,
    onLineChange,
    currency,
    formatNumber,
    taxCodes,
    allowTaxCodes,
    disabled,
    mode = 'create',
}: InvoiceLinesCardProps) {
    return (
        <Card className="border-border/70">
            <CardHeader>
                <CardTitle>Invoice lines</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {lineItems.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                        {mode === 'create'
                            ? 'Load a purchase order to see line items.'
                            : 'No invoice lines are available for editing.'}
                    </div>
                ) : (
                    <div className="space-y-4">
                        {lineItems.map((line) => {
                            const maxQty = line.remainingQuantity ?? null;
                            return (
                                <div
                                    key={line.poLineId}
                                    className="rounded-xl border border-border/60 p-4"
                                >
                                    <div className="flex flex-wrap items-center gap-3 border-b border-border/50 pb-3">
                                        {mode === 'create' ? (
                                            <Checkbox
                                                id={`include-${line.poLineId}`}
                                                checked={line.include}
                                                onCheckedChange={(checked) =>
                                                    onLineChange(
                                                        line.poLineId,
                                                        {
                                                            include:
                                                                Boolean(
                                                                    checked,
                                                                ),
                                                        },
                                                    )
                                                }
                                                disabled={disabled}
                                            />
                                        ) : (
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] tracking-wide text-muted-foreground uppercase">
                                                Line{' '}
                                                {line.lineNo ??
                                                    line.poLineId ??
                                                    line.invoiceLineId ??
                                                    '—'}
                                            </span>
                                        )}
                                        <div className="flex flex-col">
                                            <p className="text-sm font-semibold text-foreground">
                                                Line{' '}
                                                {line.lineNo ?? line.poLineId}:{' '}
                                                {line.description}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {maxQty
                                                    ? `${formatNumber(maxQty)} remaining`
                                                    : 'Qty per PO unknown'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 grid gap-4 md:grid-cols-4">
                                        <Field label="Quantity">
                                            <Input
                                                type="number"
                                                min={1}
                                                value={line.quantity}
                                                onChange={(event) =>
                                                    onLineChange(
                                                        line.poLineId,
                                                        {
                                                            quantity: Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        },
                                                    )
                                                }
                                                disabled={
                                                    disabled || !line.include
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label={`Unit price (${currency})`}
                                        >
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                value={line.unitPrice}
                                                onChange={(event) =>
                                                    onLineChange(
                                                        line.poLineId,
                                                        {
                                                            unitPrice: Number(
                                                                event.target
                                                                    .value,
                                                            ),
                                                        },
                                                    )
                                                }
                                                disabled={
                                                    disabled || !line.include
                                                }
                                            />
                                        </Field>
                                        <Field label="UOM">
                                            <Input
                                                value={line.uom ?? ''}
                                                onChange={(event) =>
                                                    onLineChange(
                                                        line.poLineId,
                                                        {
                                                            uom: event.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                                disabled={
                                                    disabled || !line.include
                                                }
                                            />
                                        </Field>
                                        <div>
                                            <Label className="text-xs tracking-wide text-muted-foreground uppercase">
                                                Line total
                                            </Label>
                                            <p className="text-lg font-semibold text-foreground">
                                                {formatNumber(
                                                    line.quantity *
                                                        line.unitPrice,
                                                    {
                                                        style: 'currency',
                                                        currency,
                                                    },
                                                )}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Automatically calculated.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                                        <Field label="Description override">
                                            <Textarea
                                                value={line.description}
                                                rows={2}
                                                onChange={(event) =>
                                                    onLineChange(
                                                        line.poLineId,
                                                        {
                                                            description:
                                                                event.target
                                                                    .value,
                                                        },
                                                    )
                                                }
                                                disabled={
                                                    disabled || !line.include
                                                }
                                            />
                                        </Field>
                                        <Field label="Tax codes">
                                            {allowTaxCodes ? (
                                                <TaxCodeMultiSelect
                                                    value={line.taxCodeIds}
                                                    options={taxCodes}
                                                    onChange={(next) =>
                                                        onLineChange(
                                                            line.poLineId,
                                                            {
                                                                taxCodeIds:
                                                                    next,
                                                            },
                                                        )
                                                    }
                                                    disabled={
                                                        disabled ||
                                                        !line.include
                                                    }
                                                />
                                            ) : (
                                                <p className="text-xs text-muted-foreground">
                                                    Tax codes are assigned by
                                                    your buyer after submission.
                                                </p>
                                            )}
                                        </Field>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

interface AttachmentsCardProps {
    documentFile: File | null;
    onFileChange: (files: File[]) => void;
    disabled?: boolean;
}

function AttachmentsCard({
    documentFile,
    onFileChange,
    disabled,
}: AttachmentsCardProps) {
    return (
        <Card className="border-border/70">
            <CardHeader>
                <CardTitle>Invoice document</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <FileDropzone
                    label={
                        documentFile
                            ? documentFile.name
                            : 'Upload signed invoice PDF'
                    }
                    description="PDF up to 50 MB."
                    accept={['application/pdf']}
                    disabled={disabled}
                    onFilesSelected={onFileChange}
                />
                {documentFile ? (
                    <Badge variant="outline" className="gap-2">
                        <BadgeDollarSign className="h-3 w-3" />{' '}
                        {documentFile.name}
                    </Badge>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Attach your official invoice to speed up approval.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

interface SubmissionCardProps {
    totals: { subtotal: number };
    onSaveDraft: () => void;
    onSubmit: () => void;
    submissionNote: string;
    onSubmissionNoteChange: (value: string) => void;
    disabledDraft: boolean;
    disabledSubmit: boolean;
    isSaving: boolean;
    draftLabel?: string;
    submitLabel?: string;
    submitPendingLabel?: string;
}

export function SubmissionCard({
    totals,
    onSaveDraft,
    onSubmit,
    submissionNote,
    onSubmissionNoteChange,
    disabledDraft,
    disabledSubmit,
    isSaving,
    draftLabel,
    submitLabel,
    submitPendingLabel,
}: SubmissionCardProps) {
    return (
        <Card className="border-border/70">
            <CardHeader>
                <CardTitle>Submit to buyer</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="rounded-lg border border-border/70 bg-muted/40 p-4">
                    <p className="text-sm text-muted-foreground">
                        Total (ex tax)
                    </p>
                    <p className="text-2xl font-semibold text-foreground">
                        {totals.subtotal.toFixed(2)}
                    </p>
                </div>
                <Field label="Buyer note (optional)">
                    <Textarea
                        rows={3}
                        placeholder="Add context like delivery references or adjustments."
                        value={submissionNote}
                        onChange={(event) =>
                            onSubmissionNoteChange(event.target.value)
                        }
                        disabled={isSaving}
                    />
                </Field>
                <div className="flex flex-wrap gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onSaveDraft}
                        disabled={disabledDraft}
                    >
                        {draftLabel ?? 'Save draft'}
                    </Button>
                    <Button
                        type="button"
                        onClick={onSubmit}
                        disabled={disabledSubmit}
                    >
                        {isSaving
                            ? (submitPendingLabel ?? 'Submitting…')
                            : (submitLabel ?? 'Submit invoice')}
                    </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                    Drafts stay private to your team. Submitted invoices notify
                    the buyer immediately.
                </p>
            </CardContent>
        </Card>
    );
}

interface FieldProps {
    label: string;
    children: React.ReactNode;
}

function Field({ label, children }: FieldProps) {
    return (
        <div className="space-y-2">
            <Label className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </Label>
            {children}
        </div>
    );
}

interface SummaryItemProps {
    label: string;
    value: string | number | null | undefined;
}

function SummaryItem({ label, value }: SummaryItemProps) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm font-semibold text-foreground">
                {value ?? '—'}
            </p>
        </div>
    );
}

interface TaxCodeMultiSelectProps {
    value: number[];
    options: Array<{
        id?: number | null;
        label: string;
        detail: string | null;
    }>;
    onChange: (next: number[]) => void;
    disabled?: boolean;
}

function TaxCodeMultiSelect({
    value,
    options,
    onChange,
    disabled,
}: TaxCodeMultiSelectProps) {
    if (options.length === 0) {
        return (
            <p className="text-xs text-muted-foreground">
                No tax codes configured.
            </p>
        );
    }

    const handleToggle = (taxId?: number | null) => {
        if (!taxId) {
            return;
        }
        if (value.includes(taxId)) {
            onChange(value.filter((entry) => entry !== taxId));
            return;
        }
        onChange([...value, taxId]);
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-2">
                {options.map((option) => {
                    const active = option.id
                        ? value.includes(option.id)
                        : false;
                    return (
                        <button
                            key={option.id ?? option.label}
                            type="button"
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs transition',
                                active
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border/70 text-muted-foreground',
                            )}
                            onClick={() => handleToggle(option.id)}
                            disabled={disabled}
                        >
                            <span>{option.label}</span>
                            {option.detail ? (
                                <span className="ml-1 text-[10px]">
                                    {option.detail}
                                </span>
                            ) : null}
                        </button>
                    );
                })}
            </div>
            {value.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    Select applicable taxes or leave blank for tax-exempt.
                </p>
            ) : null}
        </div>
    );
}
