import { Outlet, useNavigate } from 'react-router-dom';
import { useEffect } from 'react';
import { Helmet } from 'react-helmet-async';
import { ShieldAlert } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { useAuth } from '@/contexts/auth-context';

const DIGITAL_TWIN_FEATURE_KEYS = ['digital_twin_enabled', 'digital_twin.access'];
const SUPPLIER_ROLE_PREFIX = 'supplier_';

function hasDigitalTwinAccess(hasFeature: (key: string) => boolean): boolean {
    return DIGITAL_TWIN_FEATURE_KEYS.some((key) => hasFeature(key));
}

export function RequireDigitalTwinAccess() {
    const { hasFeature, state, notifyPlanLimit } = useAuth();
    const navigate = useNavigate();

    const featureEnabled = hasDigitalTwinAccess(hasFeature);
    const role = state.user?.role ?? '';
    const isSupplierRole = role.startsWith(SUPPLIER_ROLE_PREFIX);

    useEffect(() => {
        if (!featureEnabled) {
            notifyPlanLimit({
                code: 'digital_twin_disabled',
                message: 'Digital Twin Library is available on Growth plans and above.',
            });
        }
    }, [featureEnabled, notifyPlanLimit]);

    if (isSupplierRole) {
        return (
            <section className="mx-auto flex w-full max-w-3xl flex-col gap-6 py-10">
                <Helmet>
                    <title>Buyer access required • Digital Twin Library</title>
                </Helmet>
                <EmptyState
                    title="Buyer access required"
                    description="The Digital Twin Library is only available to buyer roles. Please switch back to your buyer workspace to continue."
                    icon={<ShieldAlert className="h-12 w-12 text-amber-500" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </section>
        );
    }

    if (!featureEnabled) {
        return (
            <section className="mx-auto flex w-full max-w-3xl flex-col gap-6 py-10">
                <Helmet>
                    <title>Upgrade to access Digital Twins</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Digital Twin Library requires an upgrade"
                    description="Your current plan does not include digital twins. Upgrade to unlock the curated part library and RFQ prefills."
                    icon={<ShieldAlert className="h-12 w-12 text-amber-500" />}
                    ctaLabel="View billing options"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </section>
        );
    }

    return <Outlet />;
}
