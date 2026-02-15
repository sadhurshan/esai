const COPILOT_FEATURE_FLAGS = [
    'ai_workflows_enabled',
    'approvals_enabled',
    'ai.copilot',
    'ai_copilot',
    'ai.enabled',
];

const COPILOT_PERMISSION_KEYS = ['ai.workflows.run', 'ai.workflows.approve'];

const COPILOT_ROLE_FALLBACK = new Set([
    'owner',
    'buyer_admin',
    'buyer_member',
    'buyer_requester',
    'finance',
    'platform_super',
    'platform_support',
]);

interface AuthUserLike {
    role?: string | null;
    permissions?: unknown;
}

interface AuthStateLike {
    status?: string;
    featureFlags?: Record<string, boolean>;
    user?: AuthUserLike | null;
}

interface PersonaLike {
    type?: string | null;
    role?: string | null;
}

export type AiGateReason =
    | 'unauthenticated'
    | 'loading'
    | 'missing-role'
    | 'supplier-context'
    | 'admin-console'
    | 'plan-disabled'
    | 'permission-denied';

export interface AiGateResult {
    allowed: boolean;
    reason?: AiGateReason;
}

interface CanUseAiCopilotParams {
    isAuthenticated: boolean;
    authState: AuthStateLike;
    hasFeature?: (key: string) => boolean;
    activePersona?: PersonaLike | null;
}

export function canUseAiCopilot({
    isAuthenticated,
    authState,
    hasFeature,
    activePersona,
}: CanUseAiCopilotParams): AiGateResult {
    if (!isAuthenticated) {
        return { allowed: false, reason: 'unauthenticated' };
    }

    const status = authState.status ?? 'idle';
    if (status === 'idle' || status === 'loading') {
        return { allowed: false, reason: 'loading' };
    }

    const personaType = activePersona?.type?.toLowerCase() ?? null;
    const personaRole = activePersona?.role ?? null;
    const userRole = personaRole ?? authState.user?.role ?? null;
    if (!userRole) {
        return { allowed: false, reason: 'missing-role' };
    }

    const normalizedRole = userRole.toLowerCase();
    if (normalizedRole === 'platform_super') {
        return { allowed: false, reason: 'admin-console' };
    }
    const isSupplierContext =
        personaType === 'supplier' || normalizedRole.startsWith('supplier_');
    if (isSupplierContext) {
        return { allowed: false, reason: 'supplier-context' };
    }

    const featureEnabled = COPILOT_FEATURE_FLAGS.some((flag) => {
        if (hasFeature?.(flag)) {
            return true;
        }
        const featureFlags = authState.featureFlags ?? {};
        return featureFlags[flag] === true;
    });

    if (!featureEnabled) {
        return { allowed: false, reason: 'plan-disabled' };
    }

    const permissions = extractPermissions(authState.user);
    const hasExplicitPermission =
        permissions.length > 0 &&
        COPILOT_PERMISSION_KEYS.some((permission) =>
            permissions.includes(permission),
        );
    const fallbackRoleAllowed =
        permissions.length === 0 && COPILOT_ROLE_FALLBACK.has(normalizedRole);

    if (!hasExplicitPermission && !fallbackRoleAllowed) {
        return { allowed: false, reason: 'permission-denied' };
    }

    return { allowed: true };
}

function extractPermissions(user: AuthUserLike | null | undefined): string[] {
    if (!user || typeof user !== 'object') {
        return [];
    }

    const value = (user as { permissions?: unknown }).permissions;

    if (Array.isArray(value)) {
        return value.filter(
            (entry: unknown): entry is string =>
                typeof entry === 'string' && entry.length > 0,
        );
    }

    if (value && typeof value === 'object') {
        return Object.keys(value as Record<string, unknown>);
    }

    return [];
}
