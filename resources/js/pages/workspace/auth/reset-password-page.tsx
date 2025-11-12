import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { publishToast } from '@/components/ui/use-toast';
import { usePageTitle } from '@/hooks/use-page-title';

const resetSchema = z
    .object({
        email: z.string().email('Enter a valid email address'),
        password: z.string().min(8, 'Password must be at least 8 characters'),
        password_confirmation: z.string().min(8),
    })
    .refine((data) => data.password === data.password_confirmation, {
        message: 'Passwords must match',
        path: ['password_confirmation'],
    });

type ResetSchema = z.infer<typeof resetSchema>;

export function ResetPasswordPage() {
    usePageTitle('Reset password');
    const navigate = useNavigate();
    const { token } = useParams<{ token: string }>();
    const form = useForm<ResetSchema>({
        resolver: zodResolver(resetSchema),
        defaultValues: {
            email: '',
            password: '',
            password_confirmation: '',
        },
    });

    const onSubmit = async (values: ResetSchema) => {
        try {
            const baseUrl = import.meta.env.VITE_API_BASE_URL ?? '/api';
            await fetch(`${baseUrl.replace(/\/$/, '')}/auth/reset-password`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ...values, token }),
            });

            publishToast({
                variant: 'success',
                title: 'Password updated',
                description: 'You can now sign in with your new password.',
            });
            navigate('/login');
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to reset password. Please try again.';
            publishToast({
                variant: 'destructive',
                title: 'Reset failed',
                description: message,
            });
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-12">
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="space-y-2 text-center">
                    <CardTitle className="text-2xl font-semibold">Choose a new password</CardTitle>
                    <CardDescription>Passwords must be at least 8 characters long.</CardDescription>
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
                            <Label htmlFor="password">New password</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                {...form.register('password')}
                            />
                            {form.formState.errors.password && (
                                <p className="text-xs text-destructive">{form.formState.errors.password.message}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password_confirmation">Confirm password</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                {...form.register('password_confirmation')}
                            />
                            {form.formState.errors.password_confirmation && (
                                <p className="text-xs text-destructive">
                                    {form.formState.errors.password_confirmation.message}
                                </p>
                            )}
                        </div>

                        <Button type="submit" className="w-full">
                            Reset password
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
