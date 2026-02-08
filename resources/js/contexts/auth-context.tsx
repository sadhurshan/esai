import { publishToast } from '@/components/ui/use-toast';
import { AuthApi, type LoginRequest, type RegisterDocumentPayload } from '@/sdk/auth-client';
import { HttpError, createConfiguration } from '@/sdk';
import { useCallback, useEffect, useMemo, useReducer, useRef, createContext, useContext, type ReactNode } from 'react';

const STORAGE_KEY = 'esai.auth.state';
const PLATFORM_ROLES = new Set(['platform_super', 'platform_support']);
const ADMIN_ROLES = new Set(['owner', 'buyer_admin', 'platform_super', 'platform_support']);
const PLATFORM_SUPER_ROLES = new Set(['platform_super']);
const ADMIN_CONSOLE_FEATURE_KEY = 'admin_console_enabled';

interface AuthenticatedUser {
    id: number;
    name: string;
    email: string;
    role?: string | null;
    company_id?: number | null;
    avatar_url?: string | null;
    email_verified_at?: string | null;
    has_verified_email?: boolean;
    [key: string]: unknown;
}

interface CompanySummary {
    id: number;
    name: string;
    status?: string;
    start_mode?: string | null;
    plan?: string | null;
    supplier_status?: string | null;
    directory_visibility?: string | null;
    supplier_profile_completed_at?: string | null;
    is_verified?: boolean;
    billing_status?: string | null;
    billing_read_only?: boolean;
    billing_grace_ends_at?: string | null;
    billing_lock_at?: string | null;
    requires_plan_selection?: boolean;
    [key: string]: unknown;
}

interface PlanLimitNotice {
    code?: string | null;
    message?: string | null;
    featureKey?: string | null;
}

interface Persona {
    key: string;
    type: 'buyer' | 'supplier';
    company_id: number;
    company_name?: string | null;
    company_status?: string | null;
    company_supplier_status?: string | null;
    role?: string | null;
    is_default?: boolean;
    supplier_id?: number | null;
    supplier_name?: string | null;
    supplier_company_id?: number | null;
    supplier_company_name?: string | null;
}

interface StoredAuthState {
    token: string;
    user: AuthenticatedUser;
    company?: CompanySummary | null;
    featureFlags?: Record<string, boolean>;
    plan?: string | null;
    requiresPlanSelection?: boolean;
    requiresEmailVerification?: boolean;
    needsSupplierApproval?: boolean;
    personas?: Persona[];
    activePersonaKey?: string | null;
}

interface AuthState {
    status: 'idle' | 'loading' | 'authenticated' | 'unauthenticated';
    token: string | null;
    user: AuthenticatedUser | null;
    company: CompanySummary | null;
    featureFlags: Record<string, boolean>;
    plan: string | null;
    error: string | null;
    planLimit: PlanLimitNotice | null;
    requiresPlanSelection: boolean;
    requiresEmailVerification: boolean;
    needsSupplierApproval: boolean;
    personas: Persona[];
    activePersonaKey: string | null;
}

interface LoginPayload {
    email: string;
    password: string;
    remember?: boolean;
}

interface RegisterPayload {
    name: string;
    email: string;
    password: string;
    passwordConfirmation: string;
    companyName: string;
    companyDomain: string;
    address?: string | null;
    phone?: string | null;
    country?: string | null;
    registrationNo: string;
    taxId: string;
    website: string;
    companyDocuments: RegisterDocumentPayload[];
    startMode: 'buyer' | 'supplier';
}

interface AuthContextValue {
    state: AuthState;
    isAuthenticated: boolean;
    isLoading: boolean;
    isAdmin: boolean;
    canTrainAi: boolean;
    canAccessAdminConsole: boolean;
    requiresEmailVerification: boolean;
    personas: Persona[];
    activePersona: Persona | null;
    login: (payload: LoginPayload) => Promise<AuthFlowResult>;
    register: (payload: RegisterPayload) => Promise<AuthFlowResult>;
    logout: () => void;
    refresh: () => Promise<AuthFlowResult>;
    hasFeature: (key: string) => boolean;
    getAccessToken: () => string | null;
    notifyPlanLimit: (notice: PlanLimitNotice) => void;
    clearPlanLimit: () => void;
    resendVerificationEmail: () => Promise<void>;
    switchPersona: (key: string) => Promise<void>;
}

interface AuthFlowResult {
    requiresEmailVerification: boolean;
    requiresPlanSelection: boolean;
    needsSupplierApproval: boolean;
    userRole?: string | null;
}

type AuthAction =
    | { type: 'LOGIN_REQUEST' }
    | {
          type: 'LOGIN_SUCCESS';
          payload: {
              token: string;
              user: AuthenticatedUser;
              company?: CompanySummary | null;
              featureFlags?: Record<string, boolean>;
              plan?: string | null;
              requiresPlanSelection?: boolean;
              requiresEmailVerification?: boolean;
              needsSupplierApproval?: boolean;
              personas?: Persona[];
              activePersonaKey?: string | null;
          };
      }
    | { type: 'LOGIN_FAILURE'; payload: { error: string } }
    | { type: 'LOGOUT' }
    | {
          type: 'SET_IDENTITIES';
          payload: {
              user?: AuthenticatedUser | null;
              company?: CompanySummary | null;
              featureFlags?: Record<string, boolean>;
              plan?: string | null;
              requiresPlanSelection?: boolean;
              requiresEmailVerification?: boolean;
              needsSupplierApproval?: boolean;
              personas?: Persona[];
              activePersonaKey?: string | null;
          };
      }
    | { type: 'SET_PLAN_LIMIT'; payload: PlanLimitNotice | null }
    | { type: 'SET_ACTIVE_PERSONA'; payload: string | null };

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

const initialState: AuthState = {
    status: 'idle',
    token: null,
    user: null,
    company: null,
    featureFlags: {},
    plan: null,
    error: null,
    planLimit: null,
    requiresPlanSelection: false,
    requiresEmailVerification: false,
    needsSupplierApproval: false,
    personas: [],
    activePersonaKey: null,
};

function authReducer(state: AuthState, action: AuthAction): AuthState {
    switch (action.type) {
        case 'LOGIN_REQUEST':
            return {
                ...state,
                status: 'loading',
                error: null,
            };
        case 'LOGIN_SUCCESS': {
            const featureFlags = action.payload.featureFlags ?? {};
            const personas = action.payload.personas ?? [];
            return {
                ...state,
                status: 'authenticated',
                token: action.payload.token,
                user: action.payload.user,
                company: action.payload.company ?? null,
                featureFlags,
                plan: action.payload.plan ?? state.plan ?? null,
                error: null,
                requiresPlanSelection: action.payload.requiresPlanSelection ?? false,
                requiresEmailVerification: action.payload.requiresEmailVerification ?? false,
                needsSupplierApproval: action.payload.needsSupplierApproval ?? false,
                personas,
                activePersonaKey: alignActivePersonaKey(
                    personas,
                    action.payload.activePersonaKey ?? state.activePersonaKey,
                ),
            };
        }
        case 'LOGIN_FAILURE':
            return {
                ...state,
                status: 'unauthenticated',
                token: null,
                user: null,
                company: null,
                featureFlags: {},
                plan: null,
                error: action.payload.error,
                requiresPlanSelection: false,
                requiresEmailVerification: false,
                needsSupplierApproval: false,
                personas: [],
                activePersonaKey: null,
            };
        case 'LOGOUT':
            return {
                ...initialState,
                status: 'unauthenticated',
            };
        case 'SET_IDENTITIES': {
            const personas = action.payload.personas ?? state.personas;
            const preferredPersonaKey =
                action.payload.activePersonaKey ?? (action.payload.personas ? null : state.activePersonaKey);

            return {
                ...state,
                user: action.payload.user ?? state.user,
                company: action.payload.company ?? state.company,
                featureFlags: action.payload.featureFlags ?? state.featureFlags,
                plan: action.payload.plan ?? state.plan,
                requiresPlanSelection:
                    action.payload.requiresPlanSelection ?? state.requiresPlanSelection,
                requiresEmailVerification:
                    action.payload.requiresEmailVerification ?? state.requiresEmailVerification,
                needsSupplierApproval:
                    action.payload.needsSupplierApproval ?? state.needsSupplierApproval,
                personas,
                activePersonaKey: alignActivePersonaKey(personas, preferredPersonaKey),
            };
        }
        case 'SET_PLAN_LIMIT':
            return {
                ...state,
                planLimit: action.payload,
            };
        case 'SET_ACTIVE_PERSONA':
            return {
                ...state,
                activePersonaKey: alignActivePersonaKey(state.personas, action.payload),
            };
        default:
            return state;
    }
}

function readStoredState(): AuthState {
    if (typeof window === 'undefined') {
        return { ...initialState, status: 'unauthenticated' };
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return { ...initialState, status: 'unauthenticated' };
        }

        const parsed = JSON.parse(raw) as StoredAuthState;
        if (!parsed.token || !parsed.user) {
            return { ...initialState, status: 'unauthenticated' };
        }

        return {
            status: 'authenticated',
            token: parsed.token,
            user: parsed.user,
            company: parsed.company ?? null,
            featureFlags: parsed.featureFlags ?? {},
            plan: parsed.plan ?? null,
            error: null,
            planLimit: null,
            requiresPlanSelection: parsed.requiresPlanSelection ?? false,
            requiresEmailVerification: parsed.requiresEmailVerification ?? false,
            needsSupplierApproval: parsed.needsSupplierApproval ?? false,
            personas: parsed.personas ?? [],
            activePersonaKey: parsed.activePersonaKey ?? null,
        };
    } catch (error) {
        console.error('Failed to parse stored auth state', error);
        return { ...initialState, status: 'unauthenticated' };
    }
}

function writeStateToStorage(state: AuthState) {
    if (typeof window === 'undefined') {
        return;
    }

    if (state.token && state.user) {
        const payload: StoredAuthState = {
            token: state.token,
            user: state.user,
            company: state.company ?? undefined,
            featureFlags: state.featureFlags,
            plan: state.plan ?? undefined,
            requiresPlanSelection: state.requiresPlanSelection,
            requiresEmailVerification: state.requiresEmailVerification,
            needsSupplierApproval: state.needsSupplierApproval,
            personas: state.personas,
            activePersonaKey: state.activePersonaKey ?? undefined,
        };

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        return;
    }

    window.localStorage.removeItem(STORAGE_KEY);
}

function normalizeFeatureFlags(flags: unknown): Record<string, boolean> {
    if (!flags) {
        return {};
    }

    if (Array.isArray(flags)) {
        return flags.reduce<Record<string, boolean>>((acc, flag) => {
            if (typeof flag === 'string') {
                acc[flag] = true;
            }
            return acc;
        }, {});
    }

    if (typeof flags === 'object') {
        return Object.entries(flags as Record<string, unknown>).reduce<Record<string, boolean>>(
            (acc, [key, value]) => {
                if (typeof value === 'boolean') {
                    acc[key] = value;
                }
                return acc;
            },
            {},
        );
    }

    return {};
}

function normalizeToken(payload: Record<string, unknown>): string | null {
    const directToken = payload.token;
    if (typeof directToken === 'string' && directToken.length > 0) {
        return directToken;
    }

    const nestedToken = payload.token;
    if (nestedToken && typeof nestedToken === 'object' && typeof (nestedToken as { token?: string }).token === 'string') {
        return (nestedToken as { token?: string }).token ?? null;
    }

    const bearer = payload.access_token;
    if (typeof bearer === 'string') {
        return bearer;
    }

    return null;
}

function toNumber(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
    }

    return null;
}

function normalizePersonas(payload: unknown): Persona[] {
    if (!Array.isArray(payload)) {
        return [];
    }

    const personas: Persona[] = [];

    payload.forEach((entry) => {
        if (!entry || typeof entry !== 'object') {
            return;
        }

        const record = entry as Record<string, unknown>;
        const key = typeof record.key === 'string' ? record.key : null;
        const type = record.type === 'buyer' || record.type === 'supplier' ? (record.type as 'buyer' | 'supplier') : null;
        const companyId = toNumber(record.company_id);

        if (!key || !type || companyId === null) {
            return;
        }

        const supplierId = toNumber(record.supplier_id);

        personas.push({
            key,
            type,
            company_id: companyId,
            company_name: typeof record.company_name === 'string' ? record.company_name : null,
            company_status: typeof record.company_status === 'string' ? record.company_status : null,
            company_supplier_status:
                typeof record.company_supplier_status === 'string' ? record.company_supplier_status : null,
            role: typeof record.role === 'string' ? record.role : null,
            is_default: record.is_default === true || record.is_default === 1 || record.is_default === '1',
            supplier_id: supplierId,
            supplier_name: typeof record.supplier_name === 'string' ? record.supplier_name : null,
            supplier_company_id: toNumber(record.supplier_company_id),
            supplier_company_name:
                typeof record.supplier_company_name === 'string' ? record.supplier_company_name : null,
        });
    });

    return personas;
}

function extractPersonaKey(payload: unknown): string | null {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const candidate = (payload as { key?: unknown }).key;
    return typeof candidate === 'string' && candidate.length > 0 ? candidate : null;
}

function alignActivePersonaKey(personas: Persona[], preferredKey?: string | null): string | null {
    if (personas.length === 0) {
        return null;
    }

    if (preferredKey) {
        const match = personas.find((persona) => persona.key === preferredKey);
        if (match) {
            return match.key;
        }
    }

    const defaultBuyer = personas.find((persona) => persona.type === 'buyer' && persona.is_default);
    if (defaultBuyer) {
        return defaultBuyer.key;
    }

    const anyBuyer = personas.find((persona) => persona.type === 'buyer');
    if (anyBuyer) {
        return anyBuyer.key;
    }

    return personas[0]?.key ?? null;
}

function normalizeAuthResponse(data: Record<string, unknown>) {
    const token = normalizeToken(data);
    const user = (data.user ?? data.account ?? null) as AuthenticatedUser | null;
    const company = (data.company ?? data.tenant ?? null) as CompanySummary | null;
    const rawFlags = data.feature_flags ?? data.features;
    const featureFlags = normalizeFeatureFlags(rawFlags);
    const plan = (data.plan ?? company?.plan ?? null) as string | null;
    const requiresPlanSelection = Boolean(
        (data.requires_plan_selection ?? company?.requires_plan_selection ?? false) as boolean,
    );
    const requiresEmailVerification = computeRequiresEmailVerification(data, user);
    const needsSupplierApproval = (company?.supplier_status ?? null) === 'pending';
    const personas = normalizePersonas((data as { personas?: unknown }).personas);
    const preferredKey = extractPersonaKey((data as { active_persona?: unknown }).active_persona);
    const supplierFirst = company?.start_mode === 'supplier';
    const supplierPersona = supplierFirst ? personas.find((persona) => persona.type === 'supplier') : undefined;
    const activePersonaKey = supplierPersona?.key ?? alignActivePersonaKey(personas, preferredKey);

    return {
        token,
        user,
        company,
        featureFlags,
        plan,
        requiresPlanSelection,
        requiresEmailVerification,
        needsSupplierApproval,
        personas,
        activePersonaKey,
    };
}

function computeRequiresEmailVerification(payload: Record<string, unknown>, user: AuthenticatedUser | null): boolean {
    if (typeof payload.requires_email_verification === 'boolean') {
        return payload.requires_email_verification;
    }

    if (user && typeof user === 'object') {
        const candidate = user as Record<string, unknown>;
        if (typeof candidate.has_verified_email === 'boolean') {
            return candidate.has_verified_email === false;
        }

        if ('email_verified_at' in candidate) {
            const timestamp = candidate.email_verified_at as string | null | undefined;
            return !timestamp;
        }
    }

    return false;
}

function extractFirstValidationMessage(errors: unknown): string | null {
    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const values = Object.values(errors as Record<string, unknown>);
    for (const value of values) {
        if (typeof value === 'string' && value.trim().length > 0) {
            return value;
        }

        if (Array.isArray(value)) {
            const candidate = value.find((entry) => typeof entry === 'string' && entry.trim().length > 0);
            if (candidate) {
                return candidate;
            }
        }
    }

    return null;
}

export function AuthProvider({ children, onPersonaChange }: { children: ReactNode; onPersonaChange?: () => void }) {
    const [state, dispatch] = useReducer(authReducer, initialState, readStoredState);
    const baseUrl = useMemo(() => (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, ''), []);
    const bootstrappedFromStorageRef = useRef(state.status === 'authenticated' && state.token !== null);

    const activePersonaKey = state.activePersonaKey ?? null;

    const authClient = useMemo(() => {
        return new AuthApi(
            createConfiguration({
                baseUrl,
                bearerToken: () => state.token ?? undefined,
                defaultHeaders: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                activePersona: activePersonaKey ? () => activePersonaKey : undefined,
            }),
        );
    }, [activePersonaKey, baseUrl, state.token]);

    useEffect(() => {
        writeStateToStorage(state);
    }, [state]);

    const getAccessToken = useCallback(() => state.token, [state.token]);

    const hasFeature = useCallback(
        (key: string) => {
            if (!key) {
                return false;
            }
            return state.featureFlags[key] === true;
        },
        [state.featureFlags],
    );

    const notifyPlanLimit = useCallback((notice: PlanLimitNotice) => {
        const supplierStatus = state.company?.supplier_status ?? null;
        const isSupplierStart =
            state.company?.start_mode === 'supplier' || (supplierStatus && supplierStatus !== 'none');

        if (isSupplierStart) {
            return;
        }

        dispatch({ type: 'SET_PLAN_LIMIT', payload: notice });
    }, [state.company?.start_mode, state.company?.supplier_status]);

    const clearPlanLimit = useCallback(() => {
        dispatch({ type: 'SET_PLAN_LIMIT', payload: null });
    }, []);

    const resendVerificationEmail = useCallback(async () => {
        await authClient.resendVerificationEmail();
    }, [authClient]);

    const logout = useCallback(() => {
        dispatch({ type: 'LOGOUT' });
        authClient
            .logout()
            .catch((error) => {
                console.warn('Failed to revoke session token during logout', error);
            });
        publishToast({
            variant: 'default',
            title: 'Signed out',
            description: 'You have been signed out of Elements Supply.',
        });
    }, [authClient]);

    useEffect(() => {
        if (!state.token || !bootstrappedFromStorageRef.current) {
            return;
        }

        let isCancelled = false;

        const hydrateSession = async (): Promise<void> => {
            try {
                const data = (await authClient.current()) as Record<string, unknown>;
                if (isCancelled) {
                    return;
                }

                const {
                    user,
                    company,
                    featureFlags,
                    plan,
                    requiresPlanSelection,
                    requiresEmailVerification,
                    needsSupplierApproval,
                    personas,
                    activePersonaKey,
                } = normalizeAuthResponse(data);

                dispatch({
                    type: 'SET_IDENTITIES',
                    payload: {
                        user: user ?? undefined,
                        company: company ?? undefined,
                        featureFlags,
                        plan,
                        requiresPlanSelection,
                        requiresEmailVerification,
                        needsSupplierApproval,
                        personas,
                        activePersonaKey,
                    },
                });
            } catch (error) {
                if (isCancelled) {
                    return;
                }

                if (error instanceof HttpError && error.response.status === 401) {
                    logout();
                    return;
                }

                console.error('Failed to refresh authentication session', error);
            } finally {
                bootstrappedFromStorageRef.current = false;
            }
        };

        void hydrateSession();

        return () => {
            isCancelled = true;
        };
    }, [authClient, logout, state.token]);

    const login = useCallback(
        async ({ email, password, remember }: LoginPayload) => {
            dispatch({ type: 'LOGIN_REQUEST' });

            try {
                const request: LoginRequest = {
                    email,
                    password,
                    remember: remember ?? false,
                };

                const envelope = (await authClient.login(request)) as Record<string, unknown>;
                const {
                    token,
                    user,
                    company,
                    featureFlags,
                    plan,
                    requiresPlanSelection,
                    requiresEmailVerification,
                    needsSupplierApproval,
                    personas,
                    activePersonaKey,
                } = normalizeAuthResponse(envelope);

                if (!token || !user) {
                    dispatch({
                        type: 'LOGIN_FAILURE',
                        payload: { error: 'Authentication response missing token or user.' },
                    });
                    publishToast({
                        variant: 'destructive',
                        title: 'Unexpected response',
                        description: 'Authentication response missing token or user.',
                    });
                    throw new Error('Authentication response missing token or user.');
                }

                dispatch({
                    type: 'LOGIN_SUCCESS',
                    payload: {
                        token,
                        user,
                        company: company ?? null,
                        featureFlags,
                        plan: plan ?? null,
                        requiresPlanSelection,
                        requiresEmailVerification,
                        needsSupplierApproval,
                        personas,
                        activePersonaKey,
                    },
                });

                publishToast({
                    variant: 'success',
                    title: 'Welcome back',
                    description: `Signed in as ${user.name ?? user.email}`,
                });

                return {
                    requiresEmailVerification,
                    requiresPlanSelection: requiresPlanSelection ?? false,
                    needsSupplierApproval,
                    userRole: user.role ?? null,
                };
            } catch (error) {
                let message = 'Unable to sign in. Please check your credentials.';

                if (error instanceof HttpError) {
                    const body = error.body as Record<string, unknown> | undefined;
                    if (body && typeof body.message === 'string' && body.message.length > 0) {
                        message = body.message;
                    }
                } else if (error instanceof Error && error.message) {
                    message = error.message;
                }

                dispatch({ type: 'LOGIN_FAILURE', payload: { error: message } });
                publishToast({
                    variant: 'destructive',
                    title: 'Login failed',
                    description: message,
                });

                if (error instanceof Error) {
                    throw error;
                }
                throw new Error('Unable to sign in.');
            }
        },
        [authClient],
    );

    const register = useCallback(
        async ({
            name,
            email,
            password,
            passwordConfirmation,
            companyName,
            companyDomain,
            address,
            phone,
            country,
            registrationNo,
            taxId,
            website,
            companyDocuments,
            startMode,
        }: RegisterPayload) => {
            dispatch({ type: 'LOGIN_REQUEST' });

            try {
                const normalizedDomain = companyDomain.trim().toLowerCase();
                const normalizedCountry = country ? country.trim().toUpperCase() : undefined;
                const formData = new FormData();
                formData.append('name', name.trim());
                formData.append('email', email.trim());
                formData.append('password', password);
                formData.append('password_confirmation', passwordConfirmation);
                formData.append('company_name', companyName.trim());
                formData.append('company_domain', normalizedDomain);
                formData.append('registration_no', registrationNo.trim());
                formData.append('tax_id', taxId.trim());
                formData.append('website', website.trim());
                formData.append('start_mode', startMode);

                if (address?.trim()) {
                    formData.append('address', address.trim());
                }

                if (phone?.trim()) {
                    formData.append('phone', phone.trim());
                }

                if (normalizedCountry) {
                    formData.append('country', normalizedCountry);
                }

                companyDocuments.forEach((document, index) => {
                    formData.append(`company_documents[${index}][type]`, document.type);
                    formData.append(`company_documents[${index}][file]`, document.file);
                });

                const envelope = (await authClient.register(formData)) as Record<string, unknown>;
                const {
                    token,
                    user,
                    company,
                    featureFlags,
                    plan,
                    requiresPlanSelection,
                    requiresEmailVerification,
                    needsSupplierApproval,
                    personas,
                    activePersonaKey,
                } = normalizeAuthResponse(envelope);

                const isSupplierStart = startMode === 'supplier';
                const resolvedRequiresPlanSelection = isSupplierStart ? false : requiresPlanSelection ?? false;
                const resolvedNeedsSupplierApproval = isSupplierStart || needsSupplierApproval;

                if (!token || !user) {
                    dispatch({
                        type: 'LOGIN_FAILURE',
                        payload: { error: 'Registration response missing token or user.' },
                    });
                    publishToast({
                        variant: 'destructive',
                        title: 'Unexpected response',
                        description: 'Registration response missing token or user.',
                    });
                    throw new Error('Registration response missing token or user.');
                }

                dispatch({
                    type: 'LOGIN_SUCCESS',
                    payload: {
                        token,
                        user,
                        company: company ?? null,
                        featureFlags,
                        plan: plan ?? null,
                        requiresPlanSelection: resolvedRequiresPlanSelection,
                        requiresEmailVerification,
                        needsSupplierApproval: resolvedNeedsSupplierApproval,
                        personas,
                        activePersonaKey,
                    },
                });

                publishToast({
                    variant: 'success',
                    title: 'Workspace created',
                    description: `Welcome to Elements Supply, ${user.name ?? user.email}`,
                });

                return {
                    requiresEmailVerification,
                    requiresPlanSelection: resolvedRequiresPlanSelection,
                    needsSupplierApproval: resolvedNeedsSupplierApproval,
                    userRole: user.role ?? null,
                };
            } catch (error) {
                let message = 'Unable to complete registration at this time.';
                let validationMessage: string | null = null;

                if (error instanceof HttpError) {
                    const body = error.body as Record<string, unknown> | undefined;
                    if (body && typeof body.message === 'string' && body.message.length > 0) {
                        message = body.message;
                    }

                    const errorsBag = body && typeof body === 'object' ? (body as { errors?: unknown }).errors : undefined;
                    validationMessage = extractFirstValidationMessage(errorsBag);
                } else if (error instanceof Error && error.message) {
                    message = error.message;
                }

                const toastDescription = validationMessage ?? message;

                dispatch({ type: 'LOGIN_FAILURE', payload: { error: toastDescription } });
                publishToast({
                    variant: 'destructive',
                    title: 'Registration failed',
                    description: toastDescription,
                });

                if (error instanceof Error) {
                    throw error;
                }
                throw new Error('Unable to register.');
            }
        },
        [authClient],
    );

    const refresh = useCallback(async (): Promise<AuthFlowResult> => {
        const snapshot: AuthFlowResult = {
            requiresEmailVerification: state.requiresEmailVerification,
            requiresPlanSelection: state.requiresPlanSelection,
            needsSupplierApproval: state.needsSupplierApproval,
            userRole: state.user?.role ?? null,
        };

        if (!state.token) {
            return snapshot;
        }

        try {
            const data = (await authClient.current()) as Record<string, unknown>;
            const {
                user,
                company,
                featureFlags,
                plan,
                requiresPlanSelection,
                requiresEmailVerification,
                needsSupplierApproval,
                personas,
                activePersonaKey,
            } = normalizeAuthResponse(data);

            dispatch({
                type: 'SET_IDENTITIES',
                payload: {
                    user: user ?? undefined,
                    company: company ?? undefined,
                    featureFlags,
                    plan,
                    requiresPlanSelection,
                    requiresEmailVerification,
                    needsSupplierApproval,
                    personas,
                    activePersonaKey,
                },
            });

            return {
                requiresEmailVerification,
                requiresPlanSelection,
                needsSupplierApproval,
                userRole: user?.role ?? null,
            };
        } catch (error) {
            if (error instanceof HttpError && error.response.status === 401) {
                logout();
                return {
                    requiresEmailVerification: false,
                    requiresPlanSelection: false,
                    needsSupplierApproval: false,
                    userRole: null,
                };
            }
            console.error('Failed to refresh auth state', error);
            return snapshot;
        }
    }, [authClient, logout, state.requiresEmailVerification, state.requiresPlanSelection, state.token, state.user?.role]);

    const userRole = state.user?.role ?? null;

    const isAdmin = useMemo(() => {
        const role = userRole;
        if (!role) {
            return false;
        }
        return ADMIN_ROLES.has(role);
    }, [userRole]);

    const canAccessAdminConsole = useMemo(() => {
        const role = userRole;
        if (!role) {
            return false;
        }

        if (PLATFORM_ROLES.has(role)) {
            return true;
        }

        return isAdmin && state.featureFlags[ADMIN_CONSOLE_FEATURE_KEY] === true;
    }, [isAdmin, state.featureFlags, userRole]);

    const canTrainAi = useMemo(() => {
        const role = userRole;

        if (!role) {
            return false;
        }

        if (PLATFORM_SUPER_ROLES.has(role)) {
            return true;
        }

        return PLATFORM_ROLES.has(role) && state.featureFlags.ai_training_enabled === true;
    }, [state.featureFlags, userRole]);

    const personas = state.personas;

    const activePersona = useMemo(() => {
        if (state.activePersonaKey) {
            return personas.find((persona) => persona.key === state.activePersonaKey) ?? null;
        }

        if (personas.length > 0) {
            return personas[0];
        }

        return null;
    }, [personas, state.activePersonaKey]);

    const notifyPersonaChange = useCallback(() => {
        if (typeof onPersonaChange === 'function') {
            onPersonaChange();
        }
    }, [onPersonaChange]);

    const switchPersona = useCallback(
        async (key: string) => {
            if (!key) {
                return;
            }

            try {
                const payload = (await authClient.switchPersona({ key })) as Record<string, unknown>;
                const personas = normalizePersonas((payload as { personas?: unknown }).personas);
                const nextActiveKey = alignActivePersonaKey(
                    personas,
                    extractPersonaKey((payload as { active_persona?: unknown }).active_persona) ?? key,
                );

                dispatch({
                    type: 'SET_IDENTITIES',
                    payload: {
                        personas,
                        activePersonaKey: nextActiveKey,
                    },
                });
                notifyPersonaChange();
            } catch (error) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to switch persona',
                    description: 'Please try again in a moment.',
                });

                if (error instanceof Error) {
                    throw error;
                }

                throw new Error('Unable to switch persona.');
            }
        },
        [authClient, notifyPersonaChange],
    );

    const value = useMemo<AuthContextValue>(
        () => ({
            state,
            isAuthenticated: state.status === 'authenticated' && Boolean(state.token),
            isLoading: state.status === 'loading',
            isAdmin,
            canTrainAi,
            canAccessAdminConsole,
            requiresEmailVerification: state.requiresEmailVerification,
            personas,
            activePersona,
            login,
            register,
            logout,
            refresh,
            hasFeature,
            getAccessToken,
            notifyPlanLimit,
            clearPlanLimit,
            resendVerificationEmail,
            switchPersona,
        }),
        [
            state,
            isAdmin,
            canTrainAi,
            canAccessAdminConsole,
            personas,
            activePersona,
            login,
            register,
            logout,
            refresh,
            hasFeature,
            getAccessToken,
            notifyPlanLimit,
            clearPlanLimit,
            resendVerificationEmail,
            switchPersona,
        ],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }

    return context;
}
