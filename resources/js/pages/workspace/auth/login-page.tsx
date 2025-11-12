import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { usePageTitle } from '@/hooks/use-page-title';

const loginSchema = z.object({
    email: z.string().email('Enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    remember: z.boolean().optional(),
});

type LoginSchema = z.infer<typeof loginSchema>;

export function LoginPage() {
    usePageTitle('Sign in');
    const navigate = useNavigate();
    const location = useLocation();
    const { login, isAuthenticated, state } = useAuth();
    const form = useForm<LoginSchema>({
        resolver: zodResolver(loginSchema),
        defaultValues: {
            email: '',
            password: '',
            remember: true,
        },
    });

    useEffect(() => {
        if (isAuthenticated) {
            const redirectTo = (location.state as { from?: string } | null)?.from ?? '/app';
            navigate(redirectTo, { replace: true });
        }
    }, [isAuthenticated, navigate, location.state]);

    const onSubmit = async (values: LoginSchema) => {
        try {
            await login(values);
            const redirectTo = (location.state as { from?: string } | null)?.from ?? '/app';
            navigate(redirectTo, { replace: true });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to sign in. Please try again.';
            publishToast({
                variant: 'destructive',
                title: 'Login failed',
                description: message,
            });
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-12">
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="space-y-2 text-center">
                    <CardTitle className="text-2xl font-semibold">Welcome back</CardTitle>
                    <CardDescription>Sign in to manage sourcing, orders, and supplier activity.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-4" onSubmit={form.handleSubmit(onSubmit)}>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                placeholder="you@company.com"
                                {...form.register('email')}
                            />
                            {form.formState.errors.email && (
                                <p className="text-xs text-destructive">{form.formState.errors.email.message}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                {...form.register('password')}
                            />
                            {form.formState.errors.password && (
                                <p className="text-xs text-destructive">{form.formState.errors.password.message}</p>
                            )}
                        </div>

                        <Button type="submit" className="w-full" disabled={state.status === 'loading'}>
                            {state.status === 'loading' ? 'Signing in…' : 'Sign in'}
                        </Button>

                        <Button
                            type="button"
                            variant="link"
                            className="w-full"
                            onClick={() => navigate('/forgot-password')}
                        >
                            Forgot password?
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
