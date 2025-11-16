import { useCallback, useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';
import { Loader2, CheckCircle2, AlertCircle } from 'lucide-react';

import { Branding } from '@/config/branding';
import { publishToast } from '@/components/ui/use-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';

interface CatalogPlan {
    code: string;
    name: string;
    price_usd: number | string | null;
    rfqs_per_month: number;
    invoices_per_month: number;
    users_max: number;
    storage_gb: number;
    analytics_enabled: boolean;
    risk_scores_enabled: boolean;
    approvals_enabled: boolean;
    rma_enabled: boolean;
    credit_notes_enabled: boolean;
    global_search_enabled: boolean;
    quote_revisions_enabled: boolean;
    digital_twin_enabled: boolean;
    maintenance_enabled: boolean;
    inventory_enabled: boolean;
    pr_enabled: boolean;
    multi_currency_enabled: boolean;
    tax_engine_enabled: boolean;
    localization_enabled: boolean;
    exports_enabled: boolean;
    data_export_enabled: boolean;
    is_free: boolean;
}

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
    data?: Record<string, unknown>;
};

const apiBase = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '');
const plansUrl = apiBase ? `${apiBase}/api/plans` : '/api/plans';
const planSelectionUrl = apiBase ? `${apiBase}/api/company/plan-selection` : '/api/company/plan-selection';

export function PlanSelectionPage() {
    const navigate = useNavigate();
    const { state, refresh } = useAuth();
    const { formatMoney } = useFormatting();
    const [plans, setPlans] = useState<CatalogPlan[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState<string | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);

    const requiresPlanSelection = useMemo(() => {
        if (state.status !== 'authenticated') {
            return false;
        }
        return state.requiresPlanSelection || state.company?.requires_plan_selection === true || !state.company?.plan;
    }, [state]);

    useEffect(() => {
        if (state.status === 'authenticated' && !requiresPlanSelection) {
            navigate('/app', { replace: true });
        }
    }, [state.status, requiresPlanSelection, navigate]);

    const fetchPlans = useCallback(async () => {
        setIsLoading(true);
        setLoadError(null);
        try {
            const response = await fetch(plansUrl, {
                credentials: 'include',
            });
            const payload = (await response.json()) as PlanResponseEnvelope;
            const items = payload?.data?.items ?? [];
            setPlans(items);
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

    const selectPlan = useCallback(
        async (planCode: string) => {
            setIsSubmitting(planCode);
            try {
                const response = await fetch(planSelectionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ plan_code: planCode }),
                });

                if (!response.ok) {
                    const payload = (await response.json()) as SelectionEnvelope;
                    const message = payload?.message ?? 'Unable to save plan selection.';
                    throw new Error(message);
                }

                await refresh();
                publishToast({
                    variant: 'success',
                    title: 'Plan locked in',
                    description: 'Your workspace is ready to go.',
                });
                navigate('/app', { replace: true });
            } catch (error) {
                const description = error instanceof Error ? error.message : 'Unable to save plan selection.';
                publishToast({
                    variant: 'destructive',
                    title: 'Plan selection failed',
                    description,
                });
            } finally {
                setIsSubmitting(null);
            }
        },
        [navigate, refresh],
    );

    const renderPrice = useCallback((plan: CatalogPlan) => {
        if (plan.price_usd === 0) {
            return 'Free';
        }

        if (plan.price_usd === null || plan.price_usd === undefined || plan.price_usd === '') {
            return 'Contact sales';
        }

        const amount = typeof plan.price_usd === 'number' ? plan.price_usd : Number(plan.price_usd);
        if (Number.isNaN(amount)) {
            return 'Contact sales';
        }

        const formatted = formatMoney(amount, {
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });

        return `${formatted}/yr`;
    }, [formatMoney]);

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
                <div className="mb-10 text-center space-y-3">
                    <img src={Branding.logo.symbol} alt={Branding.name} className="mx-auto h-10" />
                    <h1 className="text-3xl font-semibold text-foreground">Select your Elements Supply plan</h1>
                    <p className="text-muted-foreground">
                        Pick the workspace tier that matches your current volume. You can change plans later from Settings → Billing.
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
                                            <div key={i} className="h-4 w-full rounded bg-muted" />
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
                                        We could not load any plans. Please contact support or try again later.
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        ) : null}
                        {plans.map((plan) => (
                            <Card key={plan.code} className="flex flex-col border border-border/70">
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between">
                                        <span>{plan.name}</span>
                                        {plan.price_usd === 0 ? <Badge variant="outline">Free</Badge> : null}
                                    </CardTitle>
                                    <CardDescription>{renderPrice(plan)}</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col justify-between space-y-6">
                                    <ul className="space-y-2 text-sm">
                                        {planHighlights(plan).map((highlight) => (
                                            <li key={highlight} className="flex items-start gap-2 text-foreground">
                                                <CheckCircle2 className="h-4 w-4 text-brand-primary" />
                                                <span>{highlight}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <Button
                                        type="button"
                                        className="w-full"
                                        onClick={() => selectPlan(plan.code)}
                                        disabled={isSubmitting !== null}
                                    >
                                        {isSubmitting === plan.code ? (
                                            <span className="flex items-center gap-2">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                Saving…
                                            </span>
                                        ) : plan.price_usd === 0 ? (
                                            'Continue with Community'
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
