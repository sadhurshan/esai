import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Branding } from '@/config/branding';
import { useAuthApi } from '@/hooks/api/use-auth-api';
import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useForm } from 'react-hook-form';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

const schema = z
    .object({
        email: z
            .string({ required_error: 'Email is required.' })
            .min(1, 'Email is required.')
            .email('Enter a valid email address.'),
        password: z
            .string({ required_error: 'Password is required.' })
            .min(8, 'Password must be at least 8 characters long.'),
        passwordConfirmation: z
            .string({ required_error: 'Please confirm your password.' })
            .min(8, 'Please confirm your password.'),
    })
    .superRefine((data, ctx) => {
        if (data.password !== data.passwordConfirmation) {
            ctx.addIssue({
                code: 'custom',
                message: 'Passwords do not match.',
                path: ['passwordConfirmation'],
            });
        }
    });

type ResetPasswordForm = z.infer<typeof schema>;

export function ResetPasswordPage() {
    const { token } = useParams<{ token: string }>();
    const authApi = useAuthApi();
    const navigate = useNavigate();
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [isComplete, setIsComplete] = useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
    } = useForm<ResetPasswordForm>({
        resolver: zodResolver(schema),
        defaultValues: {
            email: '',
            password: '',
            passwordConfirmation: '',
        },
    });

    const onSubmit = handleSubmit(async (values) => {
        setSubmitError(null);
        if (!token) {
            setSubmitError('Reset token is missing or invalid.');
            return;
        }

        try {
            await authApi.resetPassword({
                email: values.email,
                password: values.password,
                password_confirmation: values.passwordConfirmation,
                token,
            });
            setIsComplete(true);
            navigate('/login', { replace: true });
        } catch (error) {
            if (error instanceof Error) {
                setSubmitError(error.message);
            } else {
                setSubmitError('Unable to reset password.');
            }
        }
    });

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Helmet>
                <title>Choose a new password • {Branding.name}</title>
            </Helmet>
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="text-center">
                    <CardTitle className="text-2xl font-semibold text-foreground">
                        Reset password
                    </CardTitle>
                    <CardDescription>
                        Set a new password to regain access to your Elements
                        Supply account.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form className="space-y-5" onSubmit={onSubmit}>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                {...register('email')}
                            />
                            {errors.email ? (
                                <p className="text-xs text-destructive">
                                    {errors.email.message}
                                </p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="password">New password</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                {...register('password')}
                            />
                            {errors.password ? (
                                <p className="text-xs text-destructive">
                                    {errors.password.message}
                                </p>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="passwordConfirmation">
                                Confirm password
                            </Label>
                            <Input
                                id="passwordConfirmation"
                                type="password"
                                autoComplete="new-password"
                                {...register('passwordConfirmation')}
                            />
                            {errors.passwordConfirmation ? (
                                <p className="text-xs text-destructive">
                                    {errors.passwordConfirmation.message}
                                </p>
                            ) : null}
                        </div>

                        {submitError ? (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {submitError}
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={isSubmitting}
                        >
                            {isSubmitting
                                ? 'Resetting password…'
                                : 'Update password'}
                        </Button>
                    </form>
                </CardContent>
                <CardFooter className="justify-center">
                    {isComplete ? (
                        <span className="text-xs text-muted-foreground">
                            Password updated. Redirecting to sign in…
                        </span>
                    ) : (
                        <Link
                            to="/login"
                            className="text-brand-primary text-xs font-medium hover:underline"
                        >
                            Back to sign in
                        </Link>
                    )}
                </CardFooter>
            </Card>
        </div>
    );
}
