import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { Helmet } from 'react-helmet-async';
import { Branding } from '@/config/branding';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuthApi } from '@/hooks/api/use-auth-api';
import { publishToast } from '@/components/ui/use-toast';

const schema = z.object({
    email: z
        .string({ required_error: 'Email is required.' })
        .min(1, 'Email is required.')
        .email('Enter a valid email address.'),
});

type ForgotPasswordForm = z.infer<typeof schema>;

export function ForgotPasswordPage() {
    const authApi = useAuthApi();
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        reset,
    } = useForm<ForgotPasswordForm>({
        resolver: zodResolver(schema),
        defaultValues: {
            email: '',
        },
    });

    const onSubmit = handleSubmit(async (values) => {
        setSubmitError(null);
        setSuccessMessage(null);
        try {
            await authApi.requestPasswordReset(values);
            setSuccessMessage('Check your email for password reset instructions.');
            publishToast({
                variant: 'success',
                title: 'Email sent',
                description: 'If an account exists for that email, you will receive reset instructions shortly.',
            });
            reset({ email: values.email });
        } catch (error) {
            if (error instanceof Error) {
                setSubmitError(error.message);
            } else {
                setSubmitError('Unable to send reset instructions.');
            }
        }
    });

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Helmet>
                <title>Reset your password • {Branding.name}</title>
            </Helmet>
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="text-center">
                    <CardTitle className="text-2xl font-semibold text-foreground">Forgot password</CardTitle>
                    <CardDescription>
                        Enter the email associated with your account and we will send you a password reset link.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-5" onSubmit={onSubmit}>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" autoComplete="email" {...register('email')} />
                            {errors.email ? <p className="text-xs text-destructive">{errors.email.message}</p> : null}
                        </div>

                        {submitError ? (
                            <Alert variant="destructive">
                                <AlertDescription>{submitError}</AlertDescription>
                            </Alert>
                        ) : null}

                        {successMessage ? (
                            <Alert>
                                <AlertDescription>{successMessage}</AlertDescription>
                            </Alert>
                        ) : null}

                        <Button type="submit" className="w-full" disabled={isSubmitting}>
                            {isSubmitting ? 'Sending instructions…' : 'Send reset link'}
                        </Button>
                    </form>
                </CardContent>
                <CardFooter className="justify-center">
                    <Link to="/login" className="text-xs font-medium text-brand-primary hover:underline">
                        Back to sign in
                    </Link>
                </CardFooter>
            </Card>
        </div>
    );
}
