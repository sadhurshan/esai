import { useEffect, useMemo, useState } from 'react';
import { Branding } from '@/config/branding';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Helmet } from 'react-helmet-async';
import { MailCheck } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/auth-context';
import { publishToast } from '@/components/ui/use-toast';

export function VerifyEmailPage() {
    const navigate = useNavigate();
    const { isAuthenticated, state, resendVerificationEmail, refresh, logout } = useAuth();
    const [isResending, setIsResending] = useState(false);
    const [isChecking, setIsChecking] = useState(false);

    const nextRoute = useMemo(() => {
        const needsPlan = state.requiresPlanSelection || state.company?.requires_plan_selection === true || !state.company?.plan;
        return needsPlan ? '/app/setup/plan' : '/app';
    }, [state.company?.plan, state.company?.requires_plan_selection, state.requiresPlanSelection]);

    useEffect(() => {
        if (!isAuthenticated) {
            navigate('/login', { replace: true });
            return;
        }

        if (!state.requiresEmailVerification) {
            navigate(nextRoute, { replace: true });
        }
    }, [isAuthenticated, navigate, nextRoute, state.requiresEmailVerification]);

    const handleResend = async () => {
        if (isResending) {
            return;
        }

        setIsResending(true);
        try {
            await resendVerificationEmail();
            const email = state.user?.email ?? 'your inbox';
            publishToast({
                variant: 'success',
                title: 'Verification email sent',
                description: `Check ${email} and click the confirmation link.`,
            });
        } catch (error) {
            let message = 'Unable to resend the verification email.';
            if (error instanceof Error && error.message) {
                message = error.message;
            }

            publishToast({
                variant: 'destructive',
                title: 'Request failed',
                description: message,
            });
        } finally {
            setIsResending(false);
        }
    };

    const handleCheckStatus = async () => {
        if (isChecking) {
            return;
        }

        setIsChecking(true);
        try {
            const result = await refresh();
            if (!result.requiresEmailVerification) {
                publishToast({
                    variant: 'success',
                    title: 'Email verified',
                    description: 'Thanks for confirming your account. Redirecting you now.',
                });
                navigate(result.requiresPlanSelection ? '/app/setup/plan' : '/app', { replace: true });
                return;
            }

            publishToast({
                variant: 'default',
                title: 'Still waiting for confirmation',
                description: 'Click the verification link in your email, then try again.',
            });
        } catch (error) {
            let message = 'Unable to refresh your verification status.';
            if (error instanceof Error && error.message) {
                message = error.message;
            }

            publishToast({
                variant: 'destructive',
                title: 'Refresh failed',
                description: message,
            });
        } finally {
            setIsChecking(false);
        }
    };

    const handleReturnToSignIn = () => {
        logout();
        navigate('/login', { replace: true });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Helmet>
                <title>Verify your email • {Branding.name}</title>
            </Helmet>
            <Card className="w-full max-w-md text-center shadow-lg">
                <CardHeader className="items-center space-y-2">
                    <MailCheck className="h-10 w-10 text-brand-primary" />
                    <CardTitle className="mt-2 text-2xl font-semibold text-foreground">Check your email</CardTitle>
                    <CardDescription>
                        We sent a verification link to <span className="font-medium text-foreground">{state.user?.email ?? 'your inbox'}</span>.
                        Follow the instructions to activate your Elements Supply account.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <Button className="w-full" onClick={handleCheckStatus} disabled={isChecking || !isAuthenticated}>
                        {isChecking ? 'Checking status…' : "I've verified my email"}
                    </Button>
                    <Button variant="outline" className="w-full" onClick={handleResend} disabled={isResending || !isAuthenticated}>
                        {isResending ? 'Sending…' : 'Resend verification email'}
                    </Button>
                </CardContent>
                <CardFooter className="flex justify-center">
                    <Button variant="ghost" onClick={handleReturnToSignIn}>
                        Return to sign in
                    </Button>
                </CardFooter>
            </Card>
        </div>
    );
}
