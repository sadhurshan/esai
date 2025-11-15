import { useEffect, useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { BadgeCheck, Download, FileWarning, Loader2, Paperclip, Printer, ShieldAlert } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { MoneyCell } from '@/components/quotes/money-cell';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/empty-state';
import { FileDropzone } from '@/components/file-dropzone';
import { CreditLineEditor, type CreditLineFormValue } from '@/components/credits/credit-line-editor';
import { useAuth } from '@/contexts/auth-context';
import { useCreditNote } from '@/hooks/api/credits/use-credit-note';
import { useAttachCreditFile } from '@/hooks/api/credits/use-attach-credit-file';
import { useIssueCreditNote } from '@/hooks/api/credits/use-issue-credit-note';
import { useApproveCreditNote } from '@/hooks/api/credits/use-approve-credit-note';
import { useUpdateCreditLines } from '@/hooks/api/credits/use-update-credit-lines';
import { formatDate } from '@/lib/format';
import type { CreditNoteDetail } from '@/types/sourcing';

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'secondary',
    pending_review: 'outline',
    issued: 'outline',
    approved: 'default',
    applied: 'default',
    rejected: 'destructive',
};

const creditLineSchema = z
    .object({
        invoiceLineId: z.number().int().positive(),
        description: z.string().nullable().optional(),
        qtyInvoiced: z.number().nonnegative(),
        qtyAlreadyCredited: z.number().nonnegative().nullable().optional(),
        qtyRemaining: z.number().nonnegative(),
        qtyToCredit: z.number({ invalid_type_error: 'Enter a quantity to credit' }).min(0, 'Quantity must be at least 0'),
        unitPriceMinor: z.number().nonnegative(),
        currency: z.string().optional(),
        uom: z.string().nullable().optional(),
    })
    .superRefine((line, ctx) => {
        if (line.qtyToCredit > line.qtyRemaining) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: 'Cannot credit more than remaining quantity',
                path: ['qtyToCredit'],
            });
        }
    });

const creditLineFormSchema = z.object({
    lines: z.array(creditLineSchema),
});

type CreditLineFormValues = {
    lines: CreditLineFormValue[];
};

export function CreditNoteDetailPage() {
    const navigate = useNavigate();
    const { creditId } = useParams();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const financeEnabled = hasFeature('finance_enabled');

    const creditNoteQuery = useCreditNote(creditId);
    const credit = creditNoteQuery.data;
    const attachCreditFile = useAttachCreditFile();
    const issueCreditNote = useIssueCreditNote();
    const approveCreditNote = useApproveCreditNote();
    const updateCreditLines = useUpdateCreditLines();
    const creditLinesForm = useForm<CreditLineFormValues>({
        resolver: zodResolver(creditLineFormSchema),
        mode: 'onChange',
        reValidateMode: 'onChange',
        defaultValues: { lines: [] },
    });

    const creditLineDefaults = useMemo(() => buildCreditLineFormValues(credit), [credit]);

    useEffect(() => {
        creditLinesForm.reset({ lines: creditLineDefaults });
    }, [creditLineDefaults, creditLinesForm]);

    const isPostingCredit = issueCreditNote.isPending || approveCreditNote.isPending;
    const isSavingLines = updateCreditLines.isPending;
    const canPostCredit = Boolean(
        credit && ['draft', 'issued', 'pending_review'].includes(credit.status ?? ''),
    );
    const canEditLines = Boolean(credit && credit.status === 'draft');
    const isLineFormDirty = creditLinesForm.formState.isDirty;
    const isLineFormValid = creditLinesForm.formState.isValid;

    const postButtonLabel = (() => {
        if (!credit) {
            return 'Post credit note';
        }

        if (credit.status === 'issued' || credit.status === 'pending_review') {
            return 'Approve credit note';
        }

        return 'Post credit note';
    })();

    const handlePostCredit = () => {
        if (!credit || !canPostCredit || isPostingCredit) {
            return;
        }

        const parsedId = Number(credit.id);
        if (!Number.isFinite(parsedId) || parsedId <= 0) {
            return;
        }

        if (credit.status === 'draft') {
            issueCreditNote.mutate({ creditNoteId: parsedId });
            return;
        }

        if (credit.status === 'issued' || credit.status === 'pending_review') {
            approveCreditNote.mutate({ creditNoteId: parsedId, decision: 'approve' });
        }
    };

    const handleFilesSelected = (files: File[]) => {
        if (!credit?.id || files.length === 0 || attachCreditFile.isPending) {
            return;
        }

        const [file] = files;
        if (!file) {
            return;
        }

        attachCreditFile.mutate({
            creditNoteId: Number(credit.id),
            file,
            filename: file.name,
        });
    };

    const handleSaveLines = creditLinesForm.handleSubmit((values) => {
        if (!credit?.id || updateCreditLines.isPending) {
            return;
        }

        const payloadLines = values.lines.map((line) => ({
            invoiceLineId: line.invoiceLineId,
            qtyToCredit: Number.isFinite(line.qtyToCredit) ? Number(line.qtyToCredit) : 0,
            description: line.description ?? null,
            uom: line.uom ?? null,
        }));

        updateCreditLines.mutate(
            {
                creditNoteId: Number(credit.id),
                lines: payloadLines,
            },
            {
                onSuccess: () => {
                    creditLinesForm.reset(values);
                },
            },
        );
    });

    if (featureFlagsLoaded && !financeEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Credit Note</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Credit notes unavailable"
                    description="Upgrade your Elements Supply plan to review and approve credit notes."
                    icon={<ShieldAlert className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings?tab=billing') }}
                />
            </div>
        );
    }

    const isLoading = creditNoteQuery.isLoading;
    const hasError = Boolean(creditNoteQuery.error);

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Credit Note</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Finance / Credit note</p>
                    {credit ? (
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold text-foreground">{credit.creditNumber}</h1>
                            <Badge variant={STATUS_VARIANTS[credit.status] ?? 'outline'} className="uppercase tracking-wide">
                                {credit.status.replace(/_/g, ' ')}
                            </Badge>
                        </div>
                    ) : (
                        <Skeleton className="h-8 w-40" />
                    )}
                    <p className="text-sm text-muted-foreground">Track supplier-issued credits tied to invoices.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => navigate('/app/credit-notes')}>
                        Back to list
                    </Button>
                    <Button type="button" variant="outline" size="sm" disabled>
                        <Printer className="mr-2 h-4 w-4" /> Export PDF
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={handlePostCredit}
                        disabled={!canPostCredit || isPostingCredit}
                    >
                        {isPostingCredit ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <BadgeCheck className="mr-2 h-4 w-4" />
                        )}
                        {postButtonLabel}
                    </Button>
                </div>
            </div>

            {hasError ? (
                <EmptyState
                    title="Unable to load credit note"
                    description="Please refresh the page or try again later."
                    icon={<FileWarning className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="Back to list"
                    ctaProps={{ onClick: () => navigate('/app/credit-notes') }}
                />
            ) : null}

            {!hasError ? (
                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {isLoading || !credit ? (
                                <div className="space-y-3">
                                    <Skeleton className="h-6 w-1/2" />
                                    <Skeleton className="h-6 w-1/3" />
                                    <Skeleton className="h-6 w-1/4" />
                                </div>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-1">
                                        <p className="text-xs uppercase text-muted-foreground">Supplier</p>
                                        <p className="font-medium">
                                            {credit.supplierName ?? '—'}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-xs uppercase text-muted-foreground">Invoice</p>
                                        {credit.invoiceId ? (
                                            <Link className="text-primary" to={`/app/invoices/${credit.invoiceId}`}>
                                                {credit.invoiceNumber ?? `INV-${credit.invoiceId}`}
                                            </Link>
                                        ) : (
                                            <p className="text-muted-foreground">—</p>
                                        )}
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-xs uppercase text-muted-foreground">Reason</p>
                                        <p>{credit.reason ?? '—'}</p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-xs uppercase text-muted-foreground">Created</p>
                                        <p>{formatDate(credit.createdAt) ?? '—'}</p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-xs uppercase text-muted-foreground">Issued</p>
                                        <p>{formatDate(credit.issuedAt) ?? '—'}</p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-xs uppercase text-muted-foreground">Currency</p>
                                        <p>{credit.currency ?? '—'}</p>
                                    </div>
                                </div>
                            )}

                            {credit ? (
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <MoneyCell amountMinor={credit.totalMinor} currency={credit.currency} label="Credit total" />
                                    <MoneyCell amountMinor={credit.balanceMinor} currency={credit.currency} label="Balance" />
                                    <MoneyCell amountMinor={credit.totalMinor} currency={credit.currency} label="Original amount" />
                                </div>
                            ) : null}

                            <div className="space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <p className="text-sm font-semibold text-foreground">Credit lines</p>
                                        {canEditLines ? (
                                            <div className="flex items-center gap-2">
                                                {isLineFormDirty ? (
                                                    <Badge variant="outline" className="text-xs uppercase text-amber-600">
                                                        Unsaved
                                                    </Badge>
                                                ) : null}
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={handleSaveLines}
                                                    disabled={!isLineFormDirty || !isLineFormValid || isSavingLines}
                                                >
                                                    {isSavingLines ? (
                                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    ) : null}
                                                    Save lines
                                                </Button>
                                            </div>
                                        ) : (
                                            <Badge variant="outline" className="text-xs uppercase">
                                                Locked
                                            </Badge>
                                        )}
                                    </div>
                                {isLoading ? (
                                    <div className="space-y-2">
                                        <Skeleton className="h-5 w-full" />
                                        <Skeleton className="h-5 w-11/12" />
                                        <Skeleton className="h-5 w-3/4" />
                                    </div>
                                ) : (
                                    <CreditLineEditor
                                        form={creditLinesForm}
                                        currency={credit?.currency}
                                        disabled={!canEditLines || isSavingLines}
                                    />
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Attachments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {isLoading ? (
                                <div className="space-y-2">
                                    <Skeleton className="h-5 w-full" />
                                    <Skeleton className="h-5 w-2/3" />
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {credit && credit.attachments.length > 0 ? (
                                        <ul className="space-y-3">
                                            {credit.attachments.map((attachment) => (
                                                <li
                                                    key={attachment.id}
                                                    className="flex items-center justify-between gap-3 rounded border border-border/60 px-3 py-2"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <Paperclip className="h-4 w-4 text-muted-foreground" />
                                                        <div>
                                                            <p className="text-sm font-medium">{attachment.filename}</p>
                                                            <p className="text-xs text-muted-foreground">{formatDate(attachment.createdAt)}</p>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                        disabled={!attachment.downloadUrl}
                                                    >
                                                        <a
                                                            href={attachment.downloadUrl ?? '#'}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            aria-label={`Download ${attachment.filename}`}
                                                        >
                                                            <Download className="mr-2 h-4 w-4" /> Download
                                                        </a>
                                                    </Button>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <div className="rounded border border-dashed border-border/60 bg-muted/20 p-6 text-center text-sm text-muted-foreground">
                                            No attachments yet.
                                        </div>
                                    )}
                                    <FileDropzone
                                        label="Upload supporting PDF"
                                        description="Only PDF credit documents are supported."
                                        accept={["application/pdf"]}
                                        disabled={!credit || attachCreditFile.isPending}
                                        onFilesSelected={handleFilesSelected}
                                    />
                                    {attachCreditFile.isPending ? (
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Loader2 className="h-4 w-4 animate-spin" /> Uploading file…
                                        </div>
                                    ) : null}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            ) : null}
        </div>
    );
}

function buildCreditLineFormValues(credit?: CreditNoteDetail | null): CreditLineFormValue[] {
    if (!credit) {
        return [];
    }

    const fallbackCurrency = credit.currency ?? credit.invoice?.currency ?? 'USD';

    return credit.lines.map((line, index) => {
        const qtyInvoiced = Number(line.qtyInvoiced ?? 0);
        const qtyAlreadyCredited = Number(line.qtyAlreadyCredited ?? 0);
        const computedRemaining = Math.max(qtyInvoiced - qtyAlreadyCredited, 0);
        const qtyToCreditRaw = Number(line.qtyToCredit ?? 0);
        const qtyRemaining = Math.max(computedRemaining, qtyToCreditRaw, 0);
        const invoiceLineId = Number(line.invoiceLineId ?? index + 1);
        const unitPriceMinor =
            line.unitPriceMinor ??
            (qtyInvoiced > 0 && line.totalMinor !== undefined
                ? Math.round(line.totalMinor / qtyInvoiced)
                : 0);

        return {
            id: line.id ?? `line-${invoiceLineId}`,
            invoiceLineId: Number.isFinite(invoiceLineId) && invoiceLineId > 0 ? invoiceLineId : index + 1,
            description: line.description ?? null,
            qtyInvoiced,
            qtyAlreadyCredited,
            qtyRemaining,
            qtyToCredit: qtyToCreditRaw > 0 ? Math.min(qtyToCreditRaw, qtyRemaining || qtyToCreditRaw) : qtyRemaining,
            unitPriceMinor,
            currency: line.currency ?? fallbackCurrency,
            uom: line.uom ?? null,
        } satisfies CreditLineFormValue;
    });
}
