import { useMemo } from 'react';

import { useAuth } from '@/contexts/auth-context';

const SUPPLIER_RISK_ALLOWED_ROLES = new Set([
    'owner',
    'buyer_admin',
    'buyer_member',
    'buyer_requester',
    'finance',
    'platform_super',
    'platform_support',
]);

const SUPPLIER_RISK_FEATURE_FLAGS = [
    'risk.access',
    'risk_scores_enabled',
    'ai.supplier_risk',
    'ai_supplier_risk',
];

export function useSupplierRiskAccess() {
    const { hasFeature, state, activePersona } = useAuth();
    const personaRole = activePersona?.role ?? null;
    const fallbackRole = state.user?.role ?? null;
    const resolvedRole = personaRole ?? fallbackRole ?? null;
    const personaType = activePersona?.type ?? null;
    const isSupplierContext =
        personaType === 'supplier' ||
        (resolvedRole?.startsWith('supplier_') ?? false);
    const authReady = state.status !== 'idle' && state.status !== 'loading';

    const planHasSupplierRisk = SUPPLIER_RISK_FEATURE_FLAGS.some((flag) =>
        hasFeature(flag),
    );
    const roleAllowed = resolvedRole
        ? SUPPLIER_RISK_ALLOWED_ROLES.has(resolvedRole)
        : false;

    const canViewSupplierRisk = authReady && roleAllowed && !isSupplierContext;
    const isSupplierRiskLocked = canViewSupplierRisk && !planHasSupplierRisk;

    return useMemo(
        () => ({
            canViewSupplierRisk,
            isSupplierRiskLocked,
            planHasSupplierRisk,
            role: resolvedRole,
        }),
        [
            canViewSupplierRisk,
            isSupplierRiskLocked,
            planHasSupplierRisk,
            resolvedRole,
        ],
    );
}
