import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { publishToast } from '@/components/ui/use-toast';
import { usePageTitle } from '@/hooks/use-page-title';

const requestSchema = z.object({
    email: z.string().email('Enter a valid email address'),
});

type RequestSchema = z.infer<typeof requestSchema>;

export function ForgotPasswordPage() {
    usePageTitle('Forgot password');
    const form = useForm<RequestSchema>({
        resolver: zodResolver(requestSchema),
        defaultValues: {
            email: '',
        },
    });

    const onSubmit = async (values: RequestSchema) => {
        try {
            const baseUrl = import.meta.env.VITE_API_BASE_URL ?? '/api';
            await fetch(`${baseUrl.replace(/\/$/, '')}/auth/forgot-password`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(values),
            });

            publishToast({
                variant: 'success',
                title: 'Reset link sent',
                description: 'Check your inbox for further instructions.',
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unable to send reset link. Please try again.';
            publishToast({
                variant: 'destructive',
                title: 'Request failed',
                description: message,
            });
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-12">
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="space-y-2 text-center">
                    <CardTitle className="text-2xl font-semibold">Reset your password</CardTitle>
                    <CardDescription>Enter your email and we will send a reset link.</CardDescription>
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

                        <Button type="submit" className="w-full">
                            Send reset link
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
