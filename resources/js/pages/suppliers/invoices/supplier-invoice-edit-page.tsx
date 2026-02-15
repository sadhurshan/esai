import { AlertTriangle, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useSubmitSupplierInvoice } from '@/hooks/api/invoices/use-submit-supplier-invoice';
import { useSupplierInvoice } from '@/hooks/api/invoices/use-supplier-invoice';
import { useUpdateSupplierInvoice } from '@/hooks/api/invoices/use-update-supplier-invoice';
import { usePo } from '@/hooks/api/pos/use-po';
import { useTaxCodes } from '@/hooks/api/use-tax-codes';
import {
    InvoiceHeaderCard,
    InvoiceLineFormState,
    InvoiceLinesCard,
    PurchaseOrderStateCard,
    SubmissionCard,
} from '@/pages/suppliers/invoices/supplier-invoice-create-page';

const MINOR_FACTOR = 100;

export function SupplierInvoiceEditPage() {
    const params = useParams<{ invoiceId?: string }>();
    const invoiceId = params.invoiceId ?? '';
    const navigate = useNavigate();
    const { state, hasFeature, activePersona } = useAuth();
    const { formatMoney, formatNumber } = useFormatting();

    const [invoiceNumber, setInvoiceNumber] = useState<string | null>(null);
    const [invoiceDate, setInvoiceDate] = useState<string | null>(null);
    const [dueDate, setDueDate] = useState<string | null>(null);
    const [currency, setCurrency] = useState<string | null>(null);
    const [lineItemOverrides, setLineItemOverrides] = useState<
        Record<number, Partial<InvoiceLineFormState>>
    >({});
    const [submissionNote, setSubmissionNote] = useState('');

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
    const shouldLoadInvoice =
        Boolean(invoiceId) && (!featureFlagsLoaded || supplierInvoicingEnabled);

    const invoiceQuery = useSupplierInvoice(
        shouldLoadInvoice ? invoiceId : undefined,
    );
    const invoice = invoiceQuery.data ?? null;
    const purchaseOrderId = invoice?.purchaseOrderId ?? 0;
    const poQuery = usePo(purchaseOrderId);

    const baseLineItems = useMemo<InvoiceLineFormState[]>(() => {
        if (!invoice) {
            return [];
        }

        return invoice.lines.map((line) => ({
            poLineId: line.poLineId,
            invoiceLineId: line.id,
            lineNo: line.poLineId,
            description: line.description,
            quantity: line.quantity,
            unitPrice: line.unitPrice,
            uom: line.uom,
            taxCodeIds: line.taxCodeIds ?? [],
            include: true,
            remainingQuantity: undefined,
        }));
    }, [invoice]);

    const lineItems = useMemo(
        () =>
            baseLineItems.map((line) => ({
                ...line,
                ...(lineItemOverrides[line.poLineId] ?? {}),
            })),
        [baseLineItems, lineItemOverrides],
    );

    const resolvedInvoiceNumber = invoiceNumber ?? invoice?.invoiceNumber ?? '';
    const resolvedInvoiceDate =
        invoiceDate ??
        invoice?.invoiceDate ??
        new Date().toISOString().slice(0, 10);
    const resolvedDueDate = dueDate ?? invoice?.dueDate ?? '';
    const resolvedCurrency = currency ?? invoice?.currency ?? 'USD';

    const canAccessBillingFeatures = ['owner', 'buyer_admin'].includes(
        state.user?.role ?? '',
    );
    const allowTaxCodePicker =
        supplierInvoicingEnabled &&
        canAccessBillingFeatures &&
        !isSupplierPersona;

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

    const formatCurrencyValue = (
        minorValue?: number | null,
        currencyCode?: string | null,
    ) =>
        formatMoney((minorValue ?? 0) / MINOR_FACTOR, {
            currency: currencyCode ?? resolvedCurrency ?? 'USD',
        });

    const selectedLines = useMemo(
        () =>
            lineItems.filter(
                (line) =>
                    line.include &&
                    line.quantity > 0 &&
                    line.unitPrice > 0 &&
                    line.invoiceLineId,
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

    const updateMutation = useUpdateSupplierInvoice();
    const submitMutation = useSubmitSupplierInvoice();
    const isSaving = updateMutation.isPending || submitMutation.isPending;

    const readyForDraft = Boolean(
        invoice &&
        resolvedInvoiceNumber.trim().length > 0 &&
        resolvedInvoiceDate &&
        selectedLines.length > 0,
    );
    const readyForSubmit = readyForDraft;

    const handleLineChange = (
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

    const handlePersist = (mode: 'draft' | 'submit') => {
        if (!invoice) {
            return;
        }

        const payloadLines = selectedLines
            .map((line) => {
                if (!line.invoiceLineId) {
                    return null;
                }
                return {
                    id: line.invoiceLineId,
                    description: line.description,
                    quantity: Math.max(1, Math.floor(line.quantity)),
                    unitPrice: Number(line.unitPrice.toFixed(4)),
                    taxCodeIds: line.taxCodeIds,
                };
            })
            .filter((line): line is NonNullable<typeof line> => Boolean(line));

        if (payloadLines.length === 0) {
            return;
        }

        updateMutation.mutate(
            {
                invoiceId: invoice.id,
                invoiceNumber: resolvedInvoiceNumber.trim(),
                invoiceDate: resolvedInvoiceDate,
                dueDate: resolvedDueDate || null,
                lines: payloadLines,
            },
            {
                onSuccess: (updated) => {
                    if (mode === 'submit') {
                        submitMutation.mutate(
                            {
                                invoiceId: updated.id,
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

                    navigate(`/app/supplier/invoices/${updated.id}`);
                },
            },
        );
    };

    if (!invoiceId) {
        return (
            <MissingInvoiceState
                title="Invoice not found"
                ctaLabel="Back to invoices"
                onCta={() => navigate('/app/supplier/invoices')}
            />
        );
    }

    if (featureFlagsLoaded && !supplierInvoicingEnabled) {
        return (
            <MissingInvoiceState
                title="Supplier portal unavailable"
                description="This workspace plan does not include supplier-authored invoicing. Request an upgrade to continue."
                icon="wallet"
                ctaLabel="Back to dashboard"
                onCta={() => navigate('/app')}
            />
        );
    }

    if (invoiceQuery.isLoading) {
        return <SupplierInvoiceEditorSkeleton />;
    }

    if (invoiceQuery.isError) {
        return (
            <MissingInvoiceState
                title="Unable to load invoice"
                description="Please retry in a few seconds or contact the buyer if the issue persists."
                ctaLabel="Retry"
                onCta={() => invoiceQuery.refetch()}
            />
        );
    }

    if (!invoice) {
        return (
            <MissingInvoiceState
                title="Invoice missing"
                description="This invoice was removed or is no longer available."
                ctaLabel="Back to invoices"
                onCta={() => navigate('/app/supplier/invoices')}
            />
        );
    }

    if (invoice.status !== 'draft') {
        return (
            <MissingInvoiceState
                title="Invoice locked"
                description="Only draft invoices can be edited."
                ctaLabel="View invoice"
                onCta={() => navigate(`/app/supplier/invoices/${invoice.id}`)}
            />
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Edit invoice {invoice.invoiceNumber}</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Supplier portal
                    </p>
                    <h1 className="text-3xl font-semibold text-foreground">
                        Edit invoice {invoice.invoiceNumber}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Update draft values before submitting to your buyer.
                    </p>
                </div>
                <Button variant="outline" size="sm" asChild>
                    <Link to={`/app/supplier/invoices/${invoice.id}`}>
                        Back to invoice
                    </Link>
                </Button>
            </div>

            {purchaseOrderId ? (
                <PurchaseOrderStateCard
                    purchaseOrder={poQuery.data ?? null}
                    poQueryStatus={{
                        isLoading: poQuery.isLoading,
                        isError: poQuery.isError,
                        refetch: poQuery.refetch,
                    }}
                    formatCurrency={(value, code) =>
                        formatCurrencyValue(value, code)
                    }
                />
            ) : null}

            <InvoiceHeaderCard
                invoiceNumber={resolvedInvoiceNumber}
                onInvoiceNumberChange={setInvoiceNumber}
                invoiceDate={resolvedInvoiceDate}
                onInvoiceDateChange={setInvoiceDate}
                dueDate={resolvedDueDate}
                onDueDateChange={setDueDate}
                currency={resolvedCurrency}
                onCurrencyChange={setCurrency}
                poCurrency={invoice.currency}
                currencyReadOnly
            />

            <InvoiceLinesCard
                lineItems={lineItems}
                onLineChange={handleLineChange}
                currency={resolvedCurrency}
                formatNumber={formatNumber}
                taxCodes={taxCodeOptions}
                allowTaxCodes={allowTaxCodePicker}
                disabled={isSaving}
                mode="edit"
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
                draftLabel="Save changes"
                submitPendingLabel="Submitting…"
            />
        </div>
    );
}

function MissingInvoiceState({
    title,
    description,
    ctaLabel,
    onCta,
    icon = 'alert',
}: {
    title: string;
    description?: string;
    ctaLabel: string;
    onCta: () => void;
    icon?: 'alert' | 'wallet';
}) {
    const Icon = icon === 'wallet' ? Wallet : AlertTriangle;
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier invoices</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />
            <EmptyState
                title={title}
                description={description}
                icon={<Icon className="h-10 w-10 text-destructive" />}
                ctaLabel={ctaLabel}
                ctaProps={{ onClick: onCta }}
            />
        </div>
    );
}

function SupplierInvoiceEditorSkeleton() {
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier invoices</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />
            <Skeleton className="h-10 w-1/4" />
            <Skeleton className="h-48 w-full" />
            <Skeleton className="h-64 w-full" />
            <Skeleton className="h-64 w-full" />
        </div>
    );
}
