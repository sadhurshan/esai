import { useAuth } from '@/contexts/auth-context';
import { Navigate, Outlet, useLocation } from 'react-router-dom';

const PLATFORM_ROLES = new Set(['platform_super', 'platform_support']);

export function RequireActivePlan() {
    const { state } = useAuth();
    const location = useLocation();

    const userRole = state.user?.role ?? null;

    if (userRole && PLATFORM_ROLES.has(userRole)) {
        return <Outlet />;
    }

    const company = state.company;
    const supplierStatus = company?.supplier_status ?? null;
    const isSupplierStart =
        company?.start_mode === 'supplier' ||
        (supplierStatus && supplierStatus !== 'none');
    const requiresPlanSelection =
        state.requiresPlanSelection ||
        company?.requires_plan_selection === true;
    const hasPlan = Boolean(company?.plan);

    const needsPlan = !isSupplierStart && (requiresPlanSelection || !hasPlan);

    if (needsPlan && location.pathname !== '/app/setup/plan') {
        return (
            <Navigate
                to="/app/setup/plan"
                replace
                state={{ from: location.pathname }}
            />
        );
    }

    return <Outlet />;
}
