import { publishToast } from '@/components/ui/use-toast';
import { HttpError, createConfiguration, type Configuration } from '@/sdk';
import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, type PropsWithChildren } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from './auth-context';

type ApiConstructor<T> = new (configuration: Configuration) => T;

interface ApiClientContextValue {
    configuration: Configuration;
    getClient: <T>(api: ApiConstructor<T>) => T;
}

const ApiClientContext = createContext<ApiClientContextValue | undefined>(undefined);

const DEFAULT_QUERY_OPTIONS = {
    queries: {
        staleTime: 30_000,
        gcTime: 120_000,
        refetchOnWindowFocus: false,
    },
};

export function ApiClientProvider({ children }: PropsWithChildren) {
    const { getAccessToken, logout, notifyPlanLimit, clearPlanLimit } = useAuth();
    const baseUrl = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '');
    const navigate = useNavigate();
    const location = useLocation();

    const handleApiError = useCallback(
        (error: unknown) => {
            if (error instanceof HttpError) {
                const status = error.response.status;
                if (status === 401) {
                    publishToast({
                        variant: 'destructive',
                        title: 'Session expired',
                        description: 'Please sign in again to continue working.',
                    });
                    logout();
                    return;
                }

                if (status === 402) {
                    const details = (error.body ?? {}) as Record<string, unknown>;
                    const message =
                        (typeof details.message === 'string' && details.message.length > 0
                            ? details.message
                            : 'Your current plan does not include this feature.') ??
                        'Your current plan does not include this feature.';
                    publishToast({
                        variant: 'destructive',
                        title: 'Upgrade required',
                        description: message,
                    });
                    notifyPlanLimit({
                        code: typeof details.error_code === 'string' ? details.error_code : undefined,
                        message,
                        featureKey: typeof details.feature === 'string' ? details.feature : undefined,
                    });
                    return;
                }

                if (status === 403) {
                    publishToast({
                        variant: 'destructive',
                        title: 'Access denied',
                        description: 'You do not have permission to perform this action.',
                    });
                    if (location.pathname !== '/app/access-denied') {
                        navigate('/app/access-denied', {
                            state: { from: location.pathname },
                        });
                    }
                    return;
                }

                publishToast({
                    variant: 'destructive',
                    title: 'Request failed',
                    description: error.message,
                });
                return;
            }

            if (error instanceof Error) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unexpected error',
                    description: error.message,
                });
            }
        },
        [location.pathname, navigate, logout, notifyPlanLimit],
    );

    const queryClient = useMemo(() => {
        return new QueryClient({
            queryCache: new QueryCache({
                onError: handleApiError,
                onSuccess: () => {
                    clearPlanLimit();
                },
            }),
            mutationCache: new MutationCache({
                onError: handleApiError,
                onSuccess: () => {
                    clearPlanLimit();
                },
            }),
            defaultOptions: {
                queries: {
                    ...DEFAULT_QUERY_OPTIONS.queries,
                    retry: (failureCount: number, error: unknown) => {
                        if (error instanceof HttpError && error.response.status < 500) {
                            return false;
                        }
                        return failureCount < 2;
                    },
                },
            },
        });
    }, [clearPlanLimit, handleApiError]);

    const configuration = useMemo(() => {
        return createConfiguration({
            baseUrl,
            bearerToken: () => getAccessToken() ?? undefined,
            defaultHeaders: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    }, [baseUrl, getAccessToken]);

    const clientsRef = useRef(new Map<ApiConstructor<unknown>, unknown>());

    useEffect(() => {
        clientsRef.current.clear();
    }, [configuration]);

    const getClient = useCallback(
        <T,>(Api: ApiConstructor<T>) => {
            const cached = clientsRef.current.get(Api);
            if (cached) {
                return cached as T;
            }

            const instance = new Api(configuration);
            clientsRef.current.set(Api, instance);
            return instance;
        },
        [configuration],
    );

    const value = useMemo<ApiClientContextValue>(
        () => ({
            configuration,
            getClient,
        }),
        [configuration, getClient],
    );

    return (
        <ApiClientContext.Provider value={value}>
            <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
        </ApiClientContext.Provider>
    );
}

export function useApiClientContext(): ApiClientContextValue {
    const context = useContext(ApiClientContext);
    if (!context) {
        throw new Error('useApiClientContext must be used within an ApiClientProvider');
    }

    return context;
}

export function useSdkClient<T>(Api: ApiConstructor<T>): T {
    const { getClient } = useApiClientContext();
    return getClient(Api);
}
