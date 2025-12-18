import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useMemo, useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { Helmet } from 'react-helmet-async';
import { FileText, Loader2, ShieldAlert } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';

import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { FileDropzone } from '@/components/file-dropzone';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import { useRfp } from '@/hooks/api/rfps/use-rfp';
import {
    useSubmitRfpProposal,
    MAX_RFP_PROPOSAL_ATTACHMENT_BYTES,
} from '@/hooks/api/rfps/use-submit-rfp-proposal';
import type { MoneySettings } from '@/sdk';
import { supplierRfpProposalSchema, type SupplierRfpProposalFormValues } from './supplier-rfp-proposal-schema';

const MIN_MINOR_UNIT = 2;

export function SupplierRfpProposalPage() {
    const navigate = useNavigate();
    const params = useParams<{ rfpId?: string }>();
    const { formatDate } = useFormatting();
    const { hasFeature, state, activePersona } = useAuth();
    const isSupplierPersona = activePersona?.type === 'supplier';
    const supplierRole =
        state.user?.role === 'supplier' || state.user?.role?.startsWith('supplier_') || isSupplierPersona;
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const supplierPortalEnabled = hasFeature('supplier_portal_enabled') || supplierRole || isSupplierPersona;
    const rfpModuleEnabled =
        isSupplierPersona || hasFeature('rfps_enabled') || hasFeature('projects_enabled') || supplierPortalEnabled;
    const canAccessRfps = !featureFlagsLoaded || (supplierPortalEnabled && rfpModuleEnabled);

    const rfpId = params.rfpId ?? '';
    const rfpQuery = useRfp(rfpId, { enabled: Boolean(rfpId) && canAccessRfps });
    const moneySettingsQuery = useMoneySettings();
    const submitProposal = useSubmitRfpProposal();

    const currencyOptions = useMemo(() => inferCurrencyOptions(moneySettingsQuery.data), [moneySettingsQuery.data]);
    const defaultCurrency = currencyOptions[0]?.value ?? 'USD';
    const minorUnit = inferMinorUnit(moneySettingsQuery.data) ?? MIN_MINOR_UNIT;

    const form = useForm<SupplierRfpProposalFormValues>({
        resolver: zodResolver(supplierRfpProposalSchema),
        defaultValues: {
            currency: defaultCurrency,
            priceTotal: '',
            leadTimeDays: 30,
            approachSummary: '',
            scheduleSummary: '',
            valueAddSummary: '',
        },
    });

    const watchedCurrency = useWatch({ control: form.control, name: 'currency' });

    useEffect(() => {
        if (defaultCurrency && form.getValues('currency') !== defaultCurrency) {
            form.setValue('currency', defaultCurrency, { shouldDirty: true });
        }
    }, [defaultCurrency, form]);

    const [attachments, setAttachments] = useState<File[]>([]);

    const handleFilesSelected = (files: File[]) => {
        if (!files.length) {
            return;
        }
        setAttachments((current) => {
            const deduped = [...current];
            files.forEach((file) => {
                const exists = deduped.some((item) => item.name === file.name && item.size === file.size);
                if (!exists) {
                    deduped.push(file);
                }
            });
            return deduped;
        });
    };

    const handleAttachmentRemove = (index: number) => {
        setAttachments((current) => current.filter((_, idx) => idx !== index));
    };

    const handleSubmit = (values: SupplierRfpProposalFormValues) => {
        if (!rfpId) {
            return;
        }

        const priceMajor = normalizePrice(values.priceTotal);
        const payload = {
            rfpId,
            supplierCompanyId: state.company?.id,
            currency: values.currency,
            priceTotal: priceMajor,
            priceTotalMinor: typeof priceMajor === 'number' ? toMinorUnits(priceMajor, minorUnit) : undefined,
            leadTimeDays: values.leadTimeDays,
            approachSummary: values.approachSummary.trim(),
            scheduleSummary: values.scheduleSummary.trim(),
            valueAddSummary: values.valueAddSummary?.trim() ? values.valueAddSummary.trim() : undefined,
            attachments,
        };

        submitProposal.mutate(payload, {
            onSuccess: () => {
                form.reset({
                    currency: defaultCurrency,
                    priceTotal: '',
                    leadTimeDays: values.leadTimeDays,
                    approachSummary: '',
                    scheduleSummary: '',
                    valueAddSummary: '',
                });
                setAttachments([]);
                navigate('/app');
            },
        });
    };

    if (featureFlagsLoaded && !supplierPortalEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier proposals</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Supplier workspace not enabled"
                    description="Ask the buyer to enable supplier portal access to submit proposals online."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                />
            </div>
        );
    }

    if (featureFlagsLoaded && !rfpModuleEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier proposals</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="RFP portal unavailable"
                    description="Upgrade your plan or contact the buyer to enable project RFP submissions."
                    icon={<ShieldAlert className="h-10 w-10 text-muted-foreground" />}
                />
            </div>
        );
    }

    if (!rfpId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier proposals</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Select an RFP"
                    description="Open this page from an RFP invitation to respond."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    if (rfpQuery.isLoading) {
        return <SupplierRfpProposalSkeleton />;
    }

    if (rfpQuery.isError || !rfpQuery.data) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier proposals</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Unable to load RFP"
                    description={rfpQuery.error?.message ?? 'This project is unavailable or access was revoked.'}
                    icon={<ShieldAlert className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    const rfp = rfpQuery.data;
    const submissionDisabled = submitProposal.isPending || rfp.status !== 'published';
    const attachmentLimitMb = Math.round(MAX_RFP_PROPOSAL_ATTACHMENT_BYTES / (1024 * 1024));

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Submit proposal · {rfp.title}</title>
            </Helmet>

            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Project RFP</p>
                    <h1 className="text-3xl font-semibold text-foreground">{rfp.title}</h1>
                    <p className="text-sm text-muted-foreground">Share pricing, schedule, and approach for the buyer to review.</p>
                </div>
                <div className="flex items-center gap-3">
                    <Badge variant={rfp.status === 'published' ? 'default' : 'secondary'} className="h-9 rounded-full px-4 text-base capitalize">
                        {humanizeStatus(rfp.status)}
                    </Badge>
                    <Button type="button" variant="outline" onClick={() => navigate(-1)}>
                        Back
                    </Button>
                </div>
            </div>

            {rfp.status !== 'published' ? (
                <Alert>
                    <AlertTitle>Proposal window closed</AlertTitle>
                    <AlertDescription>
                        This RFP is currently in the “{humanizeStatus(rfp.status)}” state. You can prepare your response, but
                        submission is disabled until the buyer publishes the project again.
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <div className="space-y-6">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle>Buyer context</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <DetailItem label="Problem & objectives" value={rfp.problemObjectives} />
                            <DetailItem label="Scope" value={rfp.scope} />
                            <DetailItem label="Timeline" value={rfp.timeline} />
                            <DetailItem label="Evaluation criteria" value={rfp.evaluationCriteria} />
                            <DetailItem label="Proposal format" value={rfp.proposalFormat} />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <DetailItem label="Published" value={formatDate(rfp.publishedAt)} />
                                <DetailItem label="Last updated" value={formatDate(rfp.updatedAt)} />
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle>Attachments</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FileDropzone
                                label="Upload proposal documents"
                                description={`PDFs, PPT, XLSX up to ${attachmentLimitMb} MB each.`}
                                multiple
                                onFilesSelected={handleFilesSelected}
                                disabled={submitProposal.isPending}
                            />
                            <AttachmentList attachments={attachments} onRemove={handleAttachmentRemove} />
                        </CardContent>
                    </Card>
                </div>

                <form className="space-y-6" onSubmit={form.handleSubmit(handleSubmit)}>
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle>Proposal summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="proposal-currency">Currency</Label>
                                    <Select
                                        value={watchedCurrency}
                                        onValueChange={(value) => form.setValue('currency', value, { shouldDirty: true })}
                                    >
                                        <SelectTrigger id="proposal-currency">
                                            <SelectValue placeholder="Select currency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencyOptions.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.formState.errors.currency ? (
                                        <p className="text-xs text-destructive">{form.formState.errors.currency.message}</p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="proposal-price">Total price (optional)</Label>
                                    <Input
                                        id="proposal-price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="e.g. 125000"
                                        {...form.register('priceTotal')}
                                    />
                                    {form.formState.errors.priceTotal ? (
                                        <p className="text-xs text-destructive">{form.formState.errors.priceTotal.message}</p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="proposal-lead">Lead time (days)</Label>
                                    <Input
                                        id="proposal-lead"
                                        type="number"
                                        min="1"
                                        step="1"
                                        {...form.register('leadTimeDays')}
                                    />
                                    {form.formState.errors.leadTimeDays ? (
                                        <p className="text-xs text-destructive">{form.formState.errors.leadTimeDays.message}</p>
                                    ) : null}
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="proposal-approach">Approach summary</Label>
                                <Textarea
                                    id="proposal-approach"
                                    rows={4}
                                    placeholder="Explain how your team will solve the buyer's objectives."
                                    {...form.register('approachSummary')}
                                />
                                {form.formState.errors.approachSummary ? (
                                    <p className="text-xs text-destructive">{form.formState.errors.approachSummary.message}</p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="proposal-schedule">Schedule summary</Label>
                                <Textarea
                                    id="proposal-schedule"
                                    rows={3}
                                    placeholder="Highlight major milestones and delivery cadence."
                                    {...form.register('scheduleSummary')}
                                />
                                {form.formState.errors.scheduleSummary ? (
                                    <p className="text-xs text-destructive">{form.formState.errors.scheduleSummary.message}</p>
                                ) : null}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="proposal-value-add">Value-add / differentiators (optional)</Label>
                                <Textarea
                                    id="proposal-value-add"
                                    rows={3}
                                    placeholder="Share assumptions, partnership ideas, or unique qualifications."
                                    {...form.register('valueAddSummary')}
                                />
                                {form.formState.errors.valueAddSummary ? (
                                    <p className="text-xs text-destructive">{form.formState.errors.valueAddSummary.message}</p>
                                ) : null}
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle>Submit</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Button type="submit" className="w-full" disabled={submissionDisabled}>
                                {submitProposal.isPending ? (
                                    <span className="inline-flex items-center gap-2">
                                        <Loader2 className="h-4 w-4 animate-spin" /> Submitting…
                                    </span>
                                ) : (
                                    'Submit proposal'
                                )}
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                We will email the buyer and add this proposal to the comparison workspace.
                            </p>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </div>
    );
}

function SupplierRfpProposalSkeleton() {
    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier proposals</title>
            </Helmet>
            <PlanUpgradeBanner />
            <Card className="border-border/70">
                <CardContent className="space-y-4 p-8">
                    <div className="h-6 w-1/3 animate-pulse rounded bg-muted" />
                    <div className="h-4 w-1/2 animate-pulse rounded bg-muted" />
                    <div className="h-4 w-2/3 animate-pulse rounded bg-muted" />
                </CardContent>
            </Card>
            <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <Card className="border-border/70">
                    <CardContent className="space-y-4 p-8">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <div key={index} className="space-y-2">
                                <div className="h-4 w-32 animate-pulse rounded bg-muted" />
                                <div className="h-5 w-full animate-pulse rounded bg-muted" />
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card className="border-border/70">
                    <CardContent className="space-y-4 p-8">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <div key={index} className="h-5 w-full animate-pulse rounded bg-muted" />
                        ))}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function DetailItem({ label, value }: { label: string; value?: string | null }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className="text-sm font-medium text-foreground whitespace-pre-line">{value && value.length ? value : '—'}</p>
        </div>
    );
}

function AttachmentList({ attachments, onRemove }: { attachments: File[]; onRemove: (index: number) => void }) {
    if (attachments.length === 0) {
        return <p className="text-sm text-muted-foreground">No files added yet.</p>;
    }

    return (
        <ul className="space-y-2">
            {attachments.map((file, index) => (
                <li key={`${file.name}-${index}`} className="flex items-center justify-between rounded-md border border-border/70 px-3 py-2 text-sm">
                    <div>
                        <p className="font-medium text-foreground">{file.name}</p>
                        <p className="text-xs text-muted-foreground">{formatFileSize(file.size)}</p>
                    </div>
                    <Button type="button" variant="ghost" size="sm" onClick={() => onRemove(index)}>
                        Remove
                    </Button>
                </li>
            ))}
        </ul>
    );
}

function inferCurrencyOptions(settings?: MoneySettings) {
    const options = new Map<string, string>();
    const pricing = settings?.pricingCurrency;
    const base = settings?.baseCurrency;

    if (pricing?.code) {
        options.set(pricing.code, `${pricing.code} · ${pricing.name ?? 'Pricing currency'}`);
    }

    if (base?.code) {
        options.set(base.code, `${base.code} · ${base.name ?? 'Base currency'}`);
    }

    if (options.size === 0) {
        options.set('USD', 'USD · United States Dollar');
    }

    return Array.from(options.entries()).map(([value, label]) => ({ value, label }));
}

function inferMinorUnit(settings?: MoneySettings) {
    const pricing = settings?.pricingCurrency?.minorUnit;
    const base = settings?.baseCurrency?.minorUnit;
    return pricing ?? base ?? MIN_MINOR_UNIT;
}

function toMinorUnits(amount: number, minorUnit: number) {
    const factor = 10 ** (minorUnit ?? MIN_MINOR_UNIT);
    return Math.round(amount * factor);
}

function normalizePrice(value?: string | null) {
    if (!value || value.trim().length === 0) {
        return undefined;
    }
    const parsed = Number(value);
    if (Number.isNaN(parsed) || parsed < 0) {
        return undefined;
    }
    return parsed;
}

function humanizeStatus(status?: string | null) {
    if (!status) {
        return 'unknown';
    }
    return status.replace(/_/g, ' ');
}

function formatFileSize(bytes: number) {
    if (!bytes || bytes <= 0) {
        return '—';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;
    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }
    return `${value.toFixed(1)} ${units[unitIndex]}`;
}
