import { publishToast } from '@/components/ui/use-toast';
import { AuthApi, type LoginRequest } from '@/sdk/auth-client';
import { HttpError, createConfiguration } from '@/sdk';
import { useCallback, useEffect, useMemo, useReducer, createContext, useContext, type ReactNode } from 'react';

const STORAGE_KEY = 'esai.auth.state';

interface AuthenticatedUser {
    id: number;
    name: string;
    email: string;
    role?: string | null;
    company_id?: number | null;
    avatar_url?: string | null;
    [key: string]: unknown;
}

interface CompanySummary {
    id: number;
    name: string;
    status?: string;
    plan?: string | null;
    [key: string]: unknown;
}

interface PlanLimitNotice {
    code?: string | null;
    message?: string | null;
    featureKey?: string | null;
}

interface StoredAuthState {
    token: string;
    user: AuthenticatedUser;
    company?: CompanySummary | null;
    featureFlags?: Record<string, boolean>;
    plan?: string | null;
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
}

interface LoginPayload {
    email: string;
    password: string;
    remember?: boolean;
}

interface AuthContextValue {
    state: AuthState;
    isAuthenticated: boolean;
    isLoading: boolean;
    login: (payload: LoginPayload) => Promise<void>;
    logout: () => void;
    refresh: () => Promise<void>;
    hasFeature: (key: string) => boolean;
    getAccessToken: () => string | null;
    notifyPlanLimit: (notice: PlanLimitNotice) => void;
    clearPlanLimit: () => void;
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
          };
      }
    | { type: 'SET_PLAN_LIMIT'; payload: PlanLimitNotice | null };

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
            return {
                ...state,
                status: 'authenticated',
                token: action.payload.token,
                user: action.payload.user,
                company: action.payload.company ?? null,
                featureFlags,
                plan: action.payload.plan ?? state.plan ?? null,
                error: null,
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
            };
        case 'LOGOUT':
            return {
                ...initialState,
                status: 'unauthenticated',
            };
        case 'SET_IDENTITIES':
            return {
                ...state,
                user: action.payload.user ?? state.user,
                company: action.payload.company ?? state.company,
                featureFlags: action.payload.featureFlags ?? state.featureFlags,
                plan: action.payload.plan ?? state.plan,
            };
        case 'SET_PLAN_LIMIT':
            return {
                ...state,
                planLimit: action.payload,
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

function normalizeAuthResponse(data: Record<string, unknown>) {
    const token = normalizeToken(data);
    const user = (data.user ?? data.account ?? null) as AuthenticatedUser | null;
    const company = (data.company ?? data.tenant ?? null) as CompanySummary | null;
    const rawFlags = data.feature_flags ?? data.features;
    const featureFlags = normalizeFeatureFlags(rawFlags);
    const plan = (data.plan ?? company?.plan ?? null) as string | null;

    return { token, user, company, featureFlags, plan };
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [state, dispatch] = useReducer(authReducer, initialState, readStoredState);
    const baseUrl = useMemo(() => (import.meta.env.VITE_API_BASE_URL ?? '/api').replace(/\/$/, ''), []);

    const authClient = useMemo(() => {
        return new AuthApi(
            createConfiguration({
                baseUrl,
                bearerToken: () => state.token ?? undefined,
                defaultHeaders: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }),
        );
    }, [baseUrl, state.token]);

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
        dispatch({ type: 'SET_PLAN_LIMIT', payload: notice });
    }, []);

    const clearPlanLimit = useCallback(() => {
        dispatch({ type: 'SET_PLAN_LIMIT', payload: null });
    }, []);

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
                const { token, user, company, featureFlags, plan } = normalizeAuthResponse(envelope);

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
                    },
                });

                publishToast({
                    variant: 'success',
                    title: 'Welcome back',
                    description: `Signed in as ${user.name ?? user.email}`,
                });
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

    const refresh = useCallback(async () => {
        if (!state.token) {
            return;
        }

        try {
            const data = (await authClient.current()) as Record<string, unknown>;
            const user = (data.user ?? data.account ?? null) as AuthenticatedUser | null;
            const company = (data.company ?? data.tenant ?? null) as CompanySummary | null;
            const featureFlags = normalizeFeatureFlags(data.feature_flags ?? data.features);
            const plan = (data.plan ?? company?.plan ?? null) as string | null;

            dispatch({
                type: 'SET_IDENTITIES',
                payload: {
                    user: user ?? undefined,
                    company: company ?? undefined,
                    featureFlags,
                    plan,
                },
            });
        } catch (error) {
            if (error instanceof HttpError && error.response.status === 401) {
                logout();
                return;
            }
            console.error('Failed to refresh auth state', error);
        }
    }, [authClient, logout, state.token]);

    const value = useMemo<AuthContextValue>(
        () => ({
            state,
            isAuthenticated: state.status === 'authenticated' && Boolean(state.token),
            isLoading: state.status === 'loading',
            login,
            logout,
            refresh,
            hasFeature,
            getAccessToken,
            notifyPlanLimit,
            clearPlanLimit,
        }),
        [state, login, logout, refresh, hasFeature, getAccessToken, notifyPlanLimit, clearPlanLimit],
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
