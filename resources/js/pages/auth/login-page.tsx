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
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useState } from 'react';

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

export function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const { login, state } = useAuth();
    const [submitError, setSubmitError] = useState<string | null>(null);
    const redirectTo = (location.state as { from?: string } | null)?.from ?? '/app';

    const {
        register,
        handleSubmit,
        control,
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
        try {
            await login(values);
            navigate(redirectTo, { replace: true });
        } catch (error) {
            if (error instanceof Error) {
                setSubmitError(error.message);
            } else {
                setSubmitError('Unable to sign in.');
            }
        }
    });

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

                        {submitError || state.error ? (
                            <Alert variant="destructive">
                                <AlertDescription>{submitError ?? state.error}</AlertDescription>
                            </Alert>
                        ) : null}

                        <Button type="submit" className="w-full" disabled={isSubmitting}>
                            {isSubmitting ? 'Signing in…' : 'Sign in'}
                        </Button>
                    </form>
                </CardContent>
                <CardFooter className="justify-center">
                    <p className="text-xs text-muted-foreground">
                        Need help accessing your account? Contact your workspace administrator.
                    </p>
                </CardFooter>
            </Card>
        </div>
    );
}
