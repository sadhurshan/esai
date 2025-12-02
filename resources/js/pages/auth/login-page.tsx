import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import { Helmet } from 'react-helmet-async';
import { useAuth } from '@/contexts/auth-context';
import { Branding } from '@/config/branding';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { HttpError } from '@/sdk';
import { isPlatformRole } from '@/constants/platform-roles';

const loginSchema = z.object({
    email: z
        .string({ required_error: 'Email is required.' })
        .min(1, 'Email is required.')
        .email('Enter a valid email address.'),
    password: z
        .string({ required_error: 'Password is required.' })
        .min(1, 'Password is required.'),
    remember: z.boolean().optional(),
});

type LoginFormValues = z.infer<typeof loginSchema>;

const APP_HOME_ROUTE = '/app';
const ADMIN_HOME_ROUTE = '/app/admin';

function resolvePostLoginRoute(role: string | null | undefined, requestedPath: string): string {
    const target = requestedPath && requestedPath.length > 0 ? requestedPath : APP_HOME_ROUTE;
    if (isPlatformRole(role) && (target === APP_HOME_ROUTE || target === `${APP_HOME_ROUTE}/`)) {
        return ADMIN_HOME_ROUTE;
    }
    return target;
}

export function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { login, state } = useAuth();
    const [submitError, setSubmitError] = useState<string | null>(null);
    const redirectTo = (location.state as { from?: string } | null)?.from ?? APP_HOME_ROUTE;

    const {
        register,
        handleSubmit,
        control,
        setError,
        clearErrors,
        formState: { errors, isSubmitting },
    } = useForm<LoginFormValues>({
        resolver: zodResolver(loginSchema),
        defaultValues: {
            email: '',
            password: '',
            remember: true,
        },
    });

    const onSubmit = handleSubmit(async (values) => {
        setSubmitError(null);
        clearErrors();
        try {
            const result = await login(values);

            if (result.requiresEmailVerification) {
                navigate('/verify-email', { replace: true });
                return;
            }

            const postLoginRole = result.userRole ?? state.user?.role ?? null;
            const isPlatformOperator = isPlatformRole(postLoginRole);

            if (result.requiresPlanSelection && !isPlatformOperator) {
                navigate('/app/setup/plan', { replace: true });
                return;
            }

            const destination = resolvePostLoginRoute(postLoginRole, redirectTo);
            navigate(destination, { replace: true });
        } catch (error) {
            if (error instanceof HttpError) {
                const body = error.body as { message?: string } | undefined;
                const serverMessage = typeof body?.message === 'string' && body.message.trim().length > 0 ? body.message : null;
                const friendlyMessage = serverMessage ?? 'Invalid credentials provided.';
                setSubmitError(friendlyMessage);
                return;
            }

            if (error instanceof Error) {
                setSubmitError(error.message);
                setError('password', { type: 'server', message: error.message });
                return;
            }

            const fallbackMessage = 'Unable to sign in.';
            setSubmitError(fallbackMessage);
            setError('password', { type: 'server', message: fallbackMessage });
        }
    });

    if (state.status === 'authenticated') {
        if (state.requiresEmailVerification) {
            return <Navigate to="/verify-email" replace />;
        }

        const role = state.user?.role ?? null;
        const isPlatformOperator = isPlatformRole(role);
        const needsPlan =
            state.requiresPlanSelection || state.company?.requires_plan_selection === true || !state.company?.plan;

        if (needsPlan && !isPlatformOperator) {
            return <Navigate to="/app/setup/plan" replace />;
        }

        const destination = resolvePostLoginRoute(role, APP_HOME_ROUTE);
        return <Navigate to={destination} replace />;
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Helmet>
                <title>Sign in • {Branding.name}</title>
            </Helmet>
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="items-center text-center">
                    <img src={Branding.logo.symbol} alt={Branding.name} className="h-10" />
                    <CardTitle className="mt-2 text-2xl font-semibold text-foreground">Welcome back</CardTitle>
                    <CardDescription>Sign in to continue to your Elements Supply workspace.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-5" onSubmit={onSubmit}>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" autoComplete="email" {...register('email')} />
                            {errors.email ? (
                                <p className="text-xs text-destructive">{errors.email.message}</p>
                            ) : null}
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password">Password</Label>
                                <Link to="/forgot-password" className="text-xs font-medium text-brand-primary hover:underline">
                                    Forgot password?
                                </Link>
                            </div>
                            <Input id="password" type="password" autoComplete="current-password" {...register('password')} />
                            {errors.password ? (
                                <p className="text-xs text-destructive">{errors.password.message}</p>
                            ) : null}
                        </div>

                        <Controller
                            control={control}
                            name="remember"
                            render={({ field }) => (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="remember"
                                        checked={Boolean(field.value)}
                                        onCheckedChange={(checked) => field.onChange(Boolean(checked))}
                                    />
                                    <Label htmlFor="remember" className="text-sm text-muted-foreground">
                                        Remember me on this device
                                    </Label>
                                </div>
                            )}
                        />

                        {submitError ? (
                            <Alert variant="destructive">
                                <AlertDescription>{submitError}</AlertDescription>
                            </Alert>
                        ) : null}

                        <Button type="submit" className="w-full" disabled={isSubmitting}>
                            {isSubmitting ? 'Signing in…' : 'Sign in'}
                        </Button>
                    </form>
                </CardContent>
                <CardFooter className="flex flex-col gap-2 text-center text-xs text-muted-foreground">
                    <p>
                        Need help accessing your account? Contact your workspace administrator.
                    </p>
                    <p>
                        New to Elements Supply?{' '}
                        <Link to="/register" className="font-medium text-brand-primary hover:underline">
                            Create a workspace
                        </Link>
                    </p>
                </CardFooter>
            </Card>
        </div>
    );
}
