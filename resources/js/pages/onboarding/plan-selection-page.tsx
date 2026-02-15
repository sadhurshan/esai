import { AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useSearchParams } from 'react-router-dom';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { publishToast } from '@/components/ui/use-toast';
import { Branding } from '@/config/branding';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import type { CatalogPlan } from '@/types/plans';

type PlanResponseEnvelope = {
    status?: string;
    message?: string;
    data?: {
        items: CatalogPlan[];
    };
};

type SelectionEnvelope = {
    status?: string;
    message?: string;
    data?: {
        company?: {
            plan?: string | null;
            requires_plan_selection?: boolean;
        };
        plan?: CatalogPlan;
    } | null;
};

const apiBase = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '');
const plansUrl = apiBase ? `${apiBase}/api/plans` : '/api/plans';
const planSelectionUrl = apiBase
    ? `${apiBase}/api/company/plan-selection`
    : '/api/company/plan-selection';
const billingCheckoutUrl = apiBase
    ? `${apiBase}/api/billing/checkout`
    : '/api/billing/checkout';
const enterpriseContactUrl = (
    import.meta.env.VITE_ENTERPRISE_CONTACT_URL ?? ''
).trim();

type CheckoutEnvelope = {
    status?: string;
    message?: string;
    data?: {
        requires_checkout?: boolean;
        checkout?: {
            provider?: string;
            session_id?: string | null;
            checkout_url?: string | null;
            status?: string | null;
        };
    };
};

export function PlanSelectionPage() {
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    const { state, refresh } = useAuth();
    const { formatMoney } = useFormatting();
    const [plans, setPlans] = useState<CatalogPlan[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState<string | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    const checkoutStatus = searchParams.get('status');

    const isChangePlanFlow = useMemo(() => {
        const mode = searchParams.get('mode');
        const changeParam = searchParams.get('change');
        return (
            mode === 'change' || changeParam === '1' || changeParam === 'true'
        );
    }, [searchParams]);

    useEffect(() => {
        if (!checkoutStatus) {
            return;
        }

        const nextParams = new URLSearchParams(searchParams);
        nextParams.delete('status');
        setSearchParams(nextParams, { replace: true });

        if (checkoutStatus === 'success') {
            void (async () => {
                await refresh();
                publishToast({
                    variant: 'success',
                    title: 'Payment confirmed',
                    description:
                        'Your plan is active. Redirecting you to the workspace…',
                });
                navigate('/app', { replace: true });
            })();
            return;
        }

        if (checkoutStatus === 'cancelled') {
            publishToast({
                variant: 'default',
                title: 'Checkout canceled',
                description:
                    'You can pick a plan again whenever you are ready.',
            });
        }
    }, [checkoutStatus, navigate, refresh, searchParams, setSearchParams]);

    const requiresPlanSelection = useMemo(() => {
        if (state.status !== 'authenticated') {
            return false;
        }
        const isSupplierStart =
            state.company?.start_mode === 'supplier' ||
            (state.company?.supplier_status &&
                state.company.supplier_status !== 'none');
        if (isSupplierStart) {
            return false;
        }
        return (
            state.requiresPlanSelection ||
            state.company?.requires_plan_selection === true ||
            !state.company?.plan
        );
    }, [state]);

    useEffect(() => {
        if (
            state.status === 'authenticated' &&
            !requiresPlanSelection &&
            !isChangePlanFlow
        ) {
            navigate('/app', { replace: true });
        }
    }, [state.status, requiresPlanSelection, isChangePlanFlow, navigate]);

    const fetchPlans = useCallback(async () => {
        setIsLoading(true);
        setLoadError(null);
        try {
            const response = await fetch(plansUrl, {
                credentials: 'include',
            });
            const payload = (await response.json()) as PlanResponseEnvelope;
            setPlans(payload?.data?.items ?? []);
        } catch (error) {
            console.error('Failed to load plans', error);
            setLoadError('Unable to load plans right now.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchPlans().catch((error) => console.error(error));
    }, [fetchPlans]);

    const requiresCheckout = useCallback(
        (plan: CatalogPlan) => !plan.is_free && Number(plan.price_usd ?? 0) > 0,
        [],
    );

    const isEnterprisePlan = useCallback((plan: CatalogPlan) => {
        const code = (plan.code ?? '').toLowerCase();
        const name = (plan.name ?? '').toLowerCase();
        return code === 'enterprise' || name === 'enterprise';
    }, []);

    const selectPlan = useCallback(
        async (plan: CatalogPlan) => {
            setIsSubmitting(plan.code);
            try {
                if (isEnterprisePlan(plan)) {
                    if (!enterpriseContactUrl) {
                        throw new Error(
                            'Enterprise contact URL is not configured.',
                        );
                    }
                    window.location.assign(enterpriseContactUrl);
                    return;
                }

                if (requiresCheckout(plan)) {
                    const checkoutResponse = await fetch(billingCheckoutUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        credentials: 'include',
                        body: JSON.stringify({ plan_code: plan.code }),
                    });

                    const checkoutPayload =
                        (await checkoutResponse.json()) as CheckoutEnvelope;

                    if (!checkoutResponse.ok) {
                        const message =
                            checkoutPayload?.message ??
                            'Unable to start checkout.';
                        throw new Error(message);
                    }

                    const checkoutUrl =
                        checkoutPayload?.data?.checkout?.checkout_url;
                    if (!checkoutUrl) {
                        throw new Error(
                            'Checkout URL was not returned by Stripe.',
                        );
                    }

                    window.location.assign(checkoutUrl);
                    return;
                }

                const response = await fetch(planSelectionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ plan_code: plan.code }),
                });

                const payload = (await response.json()) as SelectionEnvelope;

                if (!response.ok) {
                    const message =
                        payload?.message ?? 'Unable to save plan selection.';
                    throw new Error(message);
                }

                await refresh();
                publishToast({
                    variant: 'success',
                    title: 'Plan locked in',
                    description:
                        payload?.message ?? 'Your workspace is ready to go.',
                });
                navigate(isChangePlanFlow ? '/app/settings/billing' : '/app', {
                    replace: true,
                });
            } catch (error) {
                const description =
                    error instanceof Error
                        ? error.message
                        : 'Unable to save plan selection.';
                publishToast({
                    variant: 'destructive',
                    title: 'Plan selection failed',
                    description,
                });
            } finally {
                setIsSubmitting(null);
            }
        },
        [
            navigate,
            refresh,
            requiresCheckout,
            isEnterprisePlan,
            isChangePlanFlow,
        ],
    );

    const renderPrice = useCallback(
        (plan: CatalogPlan) => {
            if (plan.price_usd === 0) {
                return 'Free';
            }

            if (
                plan.price_usd === null ||
                plan.price_usd === undefined ||
                plan.price_usd === ''
            ) {
                return 'Contact sales';
            }

            const amount =
                typeof plan.price_usd === 'number'
                    ? plan.price_usd
                    : Number(plan.price_usd);
            if (Number.isNaN(amount)) {
                return 'Contact sales';
            }

            const formatted = formatMoney(amount, {
                currency: 'USD',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            });

            return `${formatted}/yr`;
        },
        [formatMoney],
    );

    const planHighlights = (plan: CatalogPlan): string[] => {
        const rfqsPerMonth = plan.rfqs_per_month ?? 0;
        const invoicesPerMonth = plan.invoices_per_month ?? 0;
        const usersMax = plan.users_max ?? 0;
        const storageGb = plan.storage_gb ?? 0;

        const highlights = [
            `${rfqsPerMonth === 0 ? 'Unlimited' : rfqsPerMonth} RFQs / month`,
            `${invoicesPerMonth === 0 ? 'Unlimited' : invoicesPerMonth} invoices / month`,
            `${usersMax === 0 ? 'Unlimited users' : `${usersMax} users`}`,
            `${storageGb === 0 ? 'Unlimited storage' : `${storageGb} GB storage`}`,
        ];

        if (plan.analytics_enabled) highlights.push('Analytics workspace');
        if (plan.risk_scores_enabled) highlights.push('Supplier risk scoring');
        if (plan.approvals_enabled) highlights.push('Approval workflows');
        if (plan.inventory_enabled) highlights.push('Inventory & MRO');
        if (plan.multi_currency_enabled) highlights.push('Multi-currency');
        if (plan.exports_enabled) highlights.push('Bulk exports');

        return highlights.slice(0, 6);
    };

    return (
        <div className="flex min-h-screen flex-col bg-muted/30">
            <Helmet>
                <title>Choose a plan • {Branding.name}</title>
            </Helmet>
            <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="mb-10 space-y-3 text-center">
                    <img
                        src={Branding.logo.symbol}
                        alt={Branding.name}
                        className="mx-auto h-10"
                    />
                    <h1 className="text-3xl font-semibold text-foreground">
                        Select your Elements Supply plan
                    </h1>
                    <p className="text-muted-foreground">
                        Pick the workspace tier that matches your current
                        volume.{' '}
                        {isChangePlanFlow
                            ? 'You are updating your existing plan; new entitlements apply immediately after checkout.'
                            : 'You can update plans later from Settings → Billing.'}
                    </p>
                    {loadError ? (
                        <div className="flex items-center justify-center gap-2 text-sm text-destructive">
                            <AlertCircle className="h-4 w-4" />
                            {loadError}
                        </div>
                    ) : null}
                </div>

                {isLoading ? (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {[...Array(3)].map((_, index) => (
                            <Card key={index} className="animate-pulse">
                                <CardHeader>
                                    <div className="h-6 w-32 rounded bg-muted" />
                                    <div className="mt-2 h-4 w-24 rounded bg-muted" />
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {[...Array(4)].map((__, i) => (
                                            <div
                                                key={i}
                                                className="h-4 w-full rounded bg-muted"
                                            />
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {plans.length === 0 ? (
                            <Card className="md:col-span-2 lg:col-span-3">
                                <CardHeader>
                                    <CardTitle>No plans available</CardTitle>
                                    <CardDescription>
                                        We could not load any plans. Please
                                        contact support or try again later.
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        ) : null}
                        {plans.map((plan) => (
                            <Card
                                key={plan.code}
                                className="flex flex-col border border-border/70"
                            >
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between">
                                        <span>{plan.name}</span>
                                        {plan.price_usd === 0 ? (
                                            <Badge variant="outline">
                                                Free
                                            </Badge>
                                        ) : null}
                                    </CardTitle>
                                    <CardDescription>
                                        {renderPrice(plan)}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col justify-between space-y-6">
                                    <ul className="space-y-2 text-sm">
                                        {planHighlights(plan).map(
                                            (highlight) => (
                                                <li
                                                    key={highlight}
                                                    className="flex items-start gap-2 text-foreground"
                                                >
                                                    <CheckCircle2 className="text-brand-primary h-4 w-4" />
                                                    <span>{highlight}</span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                    <Button
                                        type="button"
                                        className="w-full"
                                        onClick={() => selectPlan(plan)}
                                        disabled={isSubmitting !== null}
                                    >
                                        {isSubmitting === plan.code ? (
                                            <span className="flex items-center gap-2">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                Saving…
                                            </span>
                                        ) : plan.price_usd === 0 ? (
                                            'Continue with Community'
                                        ) : requiresCheckout(plan) ? (
                                            'Checkout with Stripe'
                                        ) : isEnterprisePlan(plan) ? (
                                            'Contact Sales'
                                        ) : (
                                            'Continue with this plan'
                                        )}
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
