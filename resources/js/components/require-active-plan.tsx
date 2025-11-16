import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '@/contexts/auth-context';

const ALLOWED_BILLING_STATES = new Set(['active', 'trialing']);
const PLATFORM_ROLES = new Set(['platform_super', 'platform_support']);

export function RequireActivePlan() {
    const { state } = useAuth();
    const location = useLocation();

    const userRole = state.user?.role ?? null;

    if (userRole && PLATFORM_ROLES.has(userRole)) {
        return <Outlet />;
    }

    const company = state.company;
    const requiresPlanSelection = state.requiresPlanSelection || company?.requires_plan_selection === true;
    const hasPlan = Boolean(company?.plan);
    const billingStatus = (company?.billing_status as string | undefined) ?? null;

    const isPlanActive = hasPlan && (!billingStatus || ALLOWED_BILLING_STATES.has(billingStatus));
    const needsPlan = requiresPlanSelection || !hasPlan || !isPlanActive;

    if (needsPlan && location.pathname !== '/app/setup/plan') {
        return <Navigate to="/app/setup/plan" replace state={{ from: location.pathname }} />;
    }

    return <Outlet />;
}
