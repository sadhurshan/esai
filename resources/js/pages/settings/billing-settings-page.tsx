import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    CreditCard,
    ExternalLink,
    FileText,
    Lock,
    RefreshCcw,
    Shield,
    Sparkles,
} from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import type { CatalogPlan } from '@/types/plans';

type PlanResponseEnvelope = {
    status?: string;
    message?: string;
    data?: {
        items?: CatalogPlan[];
    } | null;
};

type BillingPortalEnvelope = {
    status?: string;
    message?: string;
    data?: {
        portal?: {
            url?: string | null;
        } | null;
    } | null;
    errors?: {
        fallback_url?: string | null;
        [key: string]: unknown;
    };
};

type BillingStatusTone = 'success' | 'warning' | 'danger' | 'neutral';

type PlanFeatureDefinition = {
    key: keyof CatalogPlan;
    label: string;
    description: string;
};

type BillingInvoiceRecord = {
    id: string | null;
    number: string | null;
    status: string | null;
    currency: string | null;
    amount_due: number | null;
    amount_paid: number | null;
    amount_remaining: number | null;
    total: number | null;
    hosted_invoice_url: string | null;
    invoice_pdf: string | null;
    created_at: string | null;
    due_at: string | null;
    period_start: string | null;
    period_end: string | null;
    collection_method: string | null;
    attempt_count: number | null;
    next_payment_attempt_at: string | null;
    is_paid: boolean;
    is_attempting_collection: boolean;
};

type BillingInvoiceResponseEnvelope = {
    status?: string;
    message?: string;
    data?: {
        items?: BillingInvoiceRecord[];
    } | null;
};

const PLAN_FEATURES: PlanFeatureDefinition[] = [
    {
        key: 'analytics_enabled',
        label: 'Analytics workspace',
        description: 'KPI dashboards, history, and executive-ready charts.',
    },
    {
        key: 'risk_scores_enabled',
        label: 'Supplier risk scoring',
        description: 'Quality, delivery, and compliance risk rollups.',
    },
    {
        key: 'approvals_enabled',
        label: 'Approval workflows',
        description: 'Route RFQs, quotes, and POs through multi-level approvals.',
    },
    {
        key: 'inventory_enabled',
        label: 'Inventory & MRO',
        description: 'Track on-hand, reservations, and maintenance spares.',
    },
    {
        key: 'quote_revisions_enabled',
        label: 'Quote revisions',
        description: 'Version-controlled quote updates with audit trails.',
    },
    {
        key: 'multi_currency_enabled',
        label: 'Multi-currency',
        description: 'Quote, buy, and settle purchases in any currency.',
    },
    {
        key: 'exports_enabled',
        label: 'Bulk exports',
        description: 'Download CSV/PDF exports with audit metadata.',
    },
    {
        key: 'digital_twin_enabled',
        label: 'Digital twin',
        description: 'Asset registry, BOM context, and maintenance notes.',
    },
];

const apiBase = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '');
const plansUrl = apiBase ? `${apiBase}/api/plans` : '/api/plans';
const billingPortalUrl = apiBase ? `${apiBase}/api/billing/portal` : '/api/billing/portal';
const billingInvoicesUrl = apiBase ? `${apiBase}/api/billing/invoices` : '/api/billing/invoices';
const EMPTY_FEATURE_FLAGS: Record<string, boolean> = Object.freeze({});

export function BillingSettingsPage() {
    const navigate = useNavigate();
    const { state, refresh } = useAuth();
    const { formatMoney, formatDate } = useFormatting();

    const [plans, setPlans] = useState<CatalogPlan[]>([]);
    const [isLoadingPlans, setIsLoadingPlans] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [isRefreshingProfile, setIsRefreshingProfile] = useState(false);
    const [isLaunchingPortal, setIsLaunchingPortal] = useState(false);
    const [portalError, setPortalError] = useState<string | null>(null);
    const [portalFallbackUrl, setPortalFallbackUrl] = useState<string | null>(null);
    const [invoices, setInvoices] = useState<BillingInvoiceRecord[]>([]);
    const [isLoadingInvoices, setIsLoadingInvoices] = useState(false);
    const [invoiceError, setInvoiceError] = useState<string | null>(null);

    const company = state.company;
    const currentPlanCode = state.plan ?? company?.plan ?? null;
    const featureFlags = (state.featureFlags ?? EMPTY_FEATURE_FLAGS) as Record<string, boolean>;

    useEffect(() => {
        let cancelled = false;

        async function fetchPlans() {
            setIsLoadingPlans(true);
            setLoadError(null);
            try {
                const response = await fetch(plansUrl, { credentials: 'include' });

                if (!response.ok) {
                    throw new Error('Unable to load plans');
                }

                const payload = (await response.json()) as PlanResponseEnvelope;
                if (!cancelled) {
                    setPlans(payload?.data?.items ?? []);
                }
            } catch (error) {
                console.error('Failed to load plan catalog', error);
                if (!cancelled) {
                    setLoadError('Unable to load the plan catalog right now.');
                }
            } finally {
                if (!cancelled) {
                    setIsLoadingPlans(false);
                }
            }
        }

        fetchPlans().catch((error) => console.error(error));

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        let isActive = true;

        async function fetchInvoices() {
            setIsLoadingInvoices(true);
            setInvoiceError(null);
            try {
                const response = await fetch(billingInvoicesUrl, {
                    credentials: 'include',
                    signal: controller.signal,
                });

                const payload = (await response.json().catch(() => ({}))) as BillingInvoiceResponseEnvelope;

                if (!isActive) {
                    return;
                }

                if (!response.ok || payload?.status !== 'success') {
                    setInvoiceError(payload?.message ?? 'Unable to load invoice history right now.');
                    setInvoices([]);
                    return;
                }

                setInvoices(payload?.data?.items ?? []);
            } catch (error) {
                if (!isActive || (error instanceof DOMException && error.name === 'AbortError')) {
                    return;
                }
                console.error('Failed to load Stripe invoices', error);
                setInvoiceError('Unable to load invoice history right now.');
                setInvoices([]);
            } finally {
                if (isActive) {
                    setIsLoadingInvoices(false);
                }
            }
        }

        fetchInvoices().catch((error) => console.error(error));

        return () => {
            isActive = false;
            controller.abort();
        };
    }, []);

    const currentPlan = useMemo(() => {
        if (!currentPlanCode) {
            return null;
        }
        return plans.find((plan) => plan.code === currentPlanCode) ?? null;
    }, [plans, currentPlanCode]);

    const allowances = useMemo(() => {
        const plan = currentPlan;
        const formatLimit = (value?: number | null, unit?: string) => {
            if (value === null || value === undefined || value === 0) {
                return 'Unlimited';
            }
            return `${value.toLocaleString()}${unit ?? ''}`;
        };

        return [
            { label: 'RFQs per month', value: formatLimit(plan?.rfqs_per_month) },
            { label: 'Invoices per month', value: formatLimit(plan?.invoices_per_month) },
            { label: 'Seats', value: formatLimit(plan?.users_max) },
            { label: 'Storage', value: formatLimit(plan?.storage_gb, ' GB') },
        ];
    }, [currentPlan]);

    const planPriceLabel = useMemo(() => {
        if (!currentPlan) {
            return 'Plan pending';
        }

        if (currentPlan.price_usd === null || Number(currentPlan.price_usd) === 0) {
            return 'Included';
        }

        const amount = typeof currentPlan.price_usd === 'number'
            ? currentPlan.price_usd
            : Number(currentPlan.price_usd ?? 0);

        if (Number.isNaN(amount) || amount <= 0) {
            return 'Contact sales';
        }

        return `${formatMoney(amount, {
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        })}/yr`;
    }, [currentPlan, formatMoney]);

    const planFeatureStates = useMemo(() => {
        return PLAN_FEATURES.map((feature) => {
            const flagValue = featureFlags[feature.key as string];
            const resolved = typeof flagValue === 'boolean'
                ? flagValue
                : currentPlan
                    ? Boolean(currentPlan[feature.key])
                    : false;

            return {
                ...feature,
                enabled: resolved,
            };
        });
    }, [currentPlan, featureFlags]);

    const handleRefreshProfile = useCallback(async () => {
        setIsRefreshingProfile(true);
        try {
            await refresh();
        } finally {
            setIsRefreshingProfile(false);
        }
    }, [refresh]);

    const billingStatus = company?.billing_status ?? 'inactive';
    const billingGraceEndsAt = company?.billing_grace_ends_at ?? null;
    const billingLockAt = company?.billing_lock_at ?? null;
    const billingReadOnly = Boolean(company?.billing_read_only);

    const billingStatusDetail = useMemo(() => {
        const defaultDetail = {
            label: 'Subscription pending',
            description: 'Add a payment method to unlock billing-managed modules.',
            tone: 'neutral' as BillingStatusTone,
        };

        if (billingStatus === 'active') {
            return {
                label: 'Subscription active',
                description: 'Payments are current and all workflows are unlocked.',
                tone: 'success' as BillingStatusTone,
            };
        }

        if (billingStatus === 'trialing') {
            return {
                label: 'Trialing',
                description: 'Trial access remains active until billing is configured.',
                tone: 'success' as BillingStatusTone,
            };
        }

        if (billingStatus === 'past_due' && billingReadOnly) {
            const deadline = billingGraceEndsAt
                ? formatDate(billingGraceEndsAt, { dateStyle: 'medium' })
                : 'the grace deadline';
            return {
                label: 'Payment past due',
                description: `Workspace is read-only until ${deadline}. Update your payment method to restore write access.`,
                tone: 'warning' as BillingStatusTone,
            };
        }

        if (billingStatus === 'past_due') {
            const lockedOn = billingLockAt
                ? `Grace period expired on ${formatDate(billingLockAt, { dateStyle: 'medium' })}.`
                : 'Grace period expired.';
            return {
                label: 'Workspace locked',
                description: `${lockedOn} Update your billing details to resume activity.`,
                tone: 'danger' as BillingStatusTone,
            };
        }

        if (billingStatus === 'cancelled') {
            return {
                label: 'Subscription cancelled',
                description: 'Reactivate billing to resume purchasing workflows.',
                tone: 'warning' as BillingStatusTone,
            };
        }

        return defaultDetail;
    }, [billingGraceEndsAt, billingLockAt, billingReadOnly, billingStatus, formatDate]);

    const handleOpenBillingPortal = useCallback(async () => {
        setPortalError(null);
        setPortalFallbackUrl(null);
        setIsLaunchingPortal(true);

        try {
            const response = await fetch(billingPortalUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'include',
            });

            const payload = (await response.json().catch(() => ({}))) as BillingPortalEnvelope;

            if (!response.ok || payload?.status !== 'success') {
                setPortalError(payload?.message ?? 'Unable to open the billing portal right now.');
                const fallback = payload?.errors?.fallback_url ?? null;
                if (fallback) {
                    setPortalFallbackUrl(fallback);
                }
                return;
            }

            const portalUrl = payload?.data?.portal?.url;

            if (!portalUrl) {
                setPortalError('Stripe did not provide a billing portal link.');
                return;
            }

            window.location.assign(portalUrl);
        } catch (error) {
            console.error('Failed to open billing portal', error);
            setPortalError('Unable to reach the billing portal service. Please try again in a moment.');
        } finally {
            setIsLaunchingPortal(false);
        }
    }, []);

    const showFeatureSkeleton = isLoadingPlans && !currentPlan && Object.keys(featureFlags).length === 0;

    const renderStatusIcon = () => {
        if (billingStatusDetail.tone === 'success') {
            return <CheckCircle2 className="mt-0.5 h-5 w-5 text-emerald-500" />;
        }
        if (billingStatusDetail.tone === 'warning') {
            return <AlertTriangle className="mt-0.5 h-5 w-5 text-amber-500" />;
        }
        if (billingStatusDetail.tone === 'danger') {
            return <AlertTriangle className="mt-0.5 h-5 w-5 text-destructive" />;
        }
        return <CreditCard className="mt-0.5 h-5 w-5 text-muted-foreground" />;
    };

    const readOnlyDeadline = billingGraceEndsAt ? formatDate(billingGraceEndsAt, { dateStyle: 'medium' }) : null;
    const lockedDate = billingLockAt ? formatDate(billingLockAt, { dateStyle: 'medium' }) : null;

    const formatInvoiceAmount = useCallback((minor?: number | null, currency?: string | null) => {
        if (typeof minor !== 'number') {
            return '—';
        }
        return formatMoney(minor / 100, {
            currency: currency ?? undefined,
            fallback: '—',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }, [formatMoney]);

    const formatInvoiceDate = useCallback((value?: string | null) => {
        if (!value) {
            return '—';
        }
        return formatDate(value, { dateStyle: 'medium' });
    }, [formatDate]);

    const renderInvoiceStatus = useCallback((status?: string | null) => {
        if (!status) {
            return <Badge variant="outline">Unknown</Badge>;
        }
        const normalized = status.replace(/_/g, ' ');
        const lower = status.toLowerCase();
        const toneClasses: Record<string, string> = {
            paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
            draft: 'bg-muted text-foreground',
            open: 'bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-200',
            uncollectible: 'bg-destructive/10 text-destructive',
            void: 'bg-muted text-foreground/80',
        };
        const className = toneClasses[lower] ?? 'bg-secondary text-secondary-foreground';
        const variant = lower === 'uncollectible' ? 'destructive' : 'secondary';
        return (
            <Badge variant={variant} className={className}>
                {normalized}
            </Badge>
        );
    }, []);

    return (
        <div className="space-y-8">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-sm text-muted-foreground">Workspace</p>
                    <h1 className="text-2xl font-semibold tracking-tight">Billing & plan</h1>
                    <p className="text-sm text-muted-foreground">
                        Review your current plan, feature entitlements, and limits tied to this workspace.
                    </p>
                </div>
                <Button variant="ghost" size="sm" onClick={() => navigate('/app/settings')}>
                    <ArrowLeft className="mr-2 h-4 w-4" /> Back to settings
                </Button>
            </div>

            {loadError ? (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertTitle>Plan catalog unavailable</AlertTitle>
                    <AlertDescription>
                        {loadError} Please refresh the page or contact support if the issue persists.
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-[2fr,1fr]">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle className="text-xl">Current plan</CardTitle>
                            <CardDescription>
                                {currentPlan ? `${currentPlan.name} • ${planPriceLabel}` : 'Plan selection pending'}
                            </CardDescription>
                        </div>
                        <Badge variant="outline" className="capitalize">
                            {billingStatus.replace(/_/g, ' ')}
                        </Badge>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {allowances.map((allowance) => (
                                <div key={allowance.label} className="rounded-lg border bg-muted/40 px-4 py-3">
                                    <p className="text-xs uppercase text-muted-foreground">{allowance.label}</p>
                                    <p className="text-lg font-semibold text-foreground">{allowance.value}</p>
                                </div>
                            ))}
                        </div>
                        <Separator />
                        <div className="flex flex-wrap items-center gap-3">
                            <Button onClick={() => navigate('/app/setup/plan?mode=change')}>
                                <Sparkles className="mr-2 h-4 w-4" /> Change plan
                            </Button>
                            <Button variant="outline" onClick={handleRefreshProfile} disabled={isRefreshingProfile}>
                                <RefreshCcw className="mr-2 h-4 w-4" />
                                {isRefreshingProfile ? 'Refreshing…' : 'Refresh entitlements'}
                            </Button>
                            <Button asChild variant="ghost">
                                <a href="mailto:billing@elements.supply" className="inline-flex items-center">
                                    <Shield className="mr-2 h-4 w-4" /> Contact billing
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Payment status</CardTitle>
                        <CardDescription>Check subscription health and launch the Stripe billing portal.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="rounded-lg border bg-muted/40 px-4 py-4">
                            <div className="flex items-start gap-3">
                                {renderStatusIcon()}
                                <div>
                                    <p className="font-medium">{billingStatusDetail.label}</p>
                                    <p className="text-sm text-muted-foreground">{billingStatusDetail.description}</p>
                                </div>
                            </div>
                        </div>
                        {billingReadOnly ? (
                            <Alert>
                                <AlertTriangle className="h-4 w-4" />
                                <AlertTitle>Workspace is read-only</AlertTitle>
                                <AlertDescription>
                                    {readOnlyDeadline
                                        ? `Write actions resume after ${readOnlyDeadline} unless payment posts sooner.`
                                        : 'Update your payment method to restore write access.'}
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        {!billingReadOnly && billingStatus === 'past_due' ? (
                            <Alert variant="destructive">
                                <AlertTriangle className="h-4 w-4" />
                                <AlertTitle>Workspace locked</AlertTitle>
                                <AlertDescription>
                                    {lockedDate
                                        ? `Grace period expired on ${lockedDate}. Update billing details to resume.`
                                        : 'Grace period expired. Update billing details to resume.'}
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        {portalError ? (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertTitle>Unable to reach Stripe</AlertTitle>
                                <AlertDescription>
                                    {portalError}{' '}
                                    {portalFallbackUrl ? (
                                        <a
                                            href={portalFallbackUrl}
                                            className="underline"
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Try the fallback link
                                        </a>
                                    ) : null}
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        <div className="flex flex-col gap-3">
                            <Button onClick={handleOpenBillingPortal} disabled={isLaunchingPortal}>
                                <CreditCard className="mr-2 h-4 w-4" />
                                {isLaunchingPortal ? 'Opening portal…' : 'Open billing portal'}
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                Need help updating payment methods or invoices? Email{' '}
                                <a className="underline" href="mailto:billing@elements.supply">billing@elements.supply</a>{' '}
                                with your workspace URL.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Feature access</CardTitle>
                    <CardDescription>Live view of which modules are unlocked by your plan.</CardDescription>
                </CardHeader>
                <CardContent>
                    {showFeatureSkeleton ? (
                        <div className="grid gap-3 md:grid-cols-2">
                            {Array.from({ length: 6 }).map((_, index) => (
                                <Skeleton key={index} className="h-16 rounded-lg" />
                            ))}
                        </div>
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2">
                            {planFeatureStates.map((feature) => (
                                <div
                                    key={feature.key as string}
                                    className="flex items-start gap-3 rounded-lg border border-border/70 px-4 py-3"
                                >
                                    {feature.enabled ? (
                                        <CheckCircle2 className="mt-0.5 h-5 w-5 text-emerald-500" />
                                    ) : (
                                        <Lock className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                    )}
                                    <div>
                                        <p className="font-medium text-foreground">{feature.label}</p>
                                        <p className="text-sm text-muted-foreground">{feature.description}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Invoice history</CardTitle>
                    <CardDescription>Recent Stripe invoices with quick access to hosted links and PDFs.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {invoiceError ? (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>Unable to load invoices</AlertTitle>
                            <AlertDescription>{invoiceError}</AlertDescription>
                        </Alert>
                    ) : null}
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="min-w-full divide-y text-sm">
                            <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">Invoice</th>
                                    <th className="px-4 py-3 text-left font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Total</th>
                                    <th className="px-4 py-3 text-right font-medium">Amount due</th>
                                    <th className="px-4 py-3 text-left font-medium">Due date</th>
                                    <th className="px-4 py-3 text-left font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {isLoadingInvoices
                                    ? Array.from({ length: 3 }).map((_, index) => (
                                          <tr key={`invoice-skeleton-${index}`}>
                                              <td className="px-4 py-4">
                                                  <Skeleton className="h-4 w-32" />
                                                  <Skeleton className="mt-2 h-3 w-20" />
                                              </td>
                                              <td className="px-4 py-4">
                                                  <Skeleton className="h-5 w-16" />
                                              </td>
                                              <td className="px-4 py-4 text-right">
                                                  <Skeleton className="ml-auto h-4 w-20" />
                                              </td>
                                              <td className="px-4 py-4 text-right">
                                                  <Skeleton className="ml-auto h-4 w-20" />
                                              </td>
                                              <td className="px-4 py-4">
                                                  <Skeleton className="h-4 w-24" />
                                              </td>
                                              <td className="px-4 py-4">
                                                  <Skeleton className="h-8 w-32" />
                                              </td>
                                          </tr>
                                      ))
                                    : invoices.length > 0
                                      ? invoices.map((invoice, index) => (
                                          <tr
                                            key={invoice.id ?? invoice.number ?? invoice.created_at ?? `invoice-${index}`}
                                          >
                                                <td className="px-4 py-4 align-top">
                                                    <div className="font-medium text-foreground">
                                                        {invoice.number ?? invoice.id ?? '—'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Issued {formatInvoiceDate(invoice.created_at)}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 align-top">{renderInvoiceStatus(invoice.status)}</td>
                                                <td className="px-4 py-4 text-right align-top">
                                                    {formatInvoiceAmount(invoice.total, invoice.currency ?? undefined)}
                                                </td>
                                                <td className="px-4 py-4 text-right align-top">
                                                    {formatInvoiceAmount(
                                                        invoice.amount_remaining ?? invoice.amount_due,
                                                        invoice.currency ?? undefined,
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    {formatInvoiceDate(invoice.due_at)}
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <div className="flex flex-wrap gap-2">
                                                        {invoice.hosted_invoice_url ? (
                                                            <Button asChild variant="outline" size="sm">
                                                                <a
                                                                    href={invoice.hosted_invoice_url}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <ExternalLink className="mr-1 h-3.5 w-3.5" />
                                                                    View
                                                                </a>
                                                            </Button>
                                                        ) : null}
                                                        {invoice.invoice_pdf ? (
                                                            <Button asChild variant="ghost" size="sm">
                                                                <a
                                                                    href={invoice.invoice_pdf}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    <FileText className="mr-1 h-3.5 w-3.5" />
                                                                    PDF
                                                                </a>
                                                            </Button>
                                                        ) : null}
                                                        {!invoice.hosted_invoice_url && !invoice.invoice_pdf ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                No files available
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                      : (
                                            <tr>
                                                <td className="px-4 py-8 text-center text-sm text-muted-foreground" colSpan={6}>
                                                    {invoiceError
                                                        ? 'Invoice history is unavailable right now.'
                                                        : 'No Stripe invoices have been generated yet.'}
                                                </td>
                                            </tr>
                                        )}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
