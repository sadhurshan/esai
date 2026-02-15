import { useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, Loader2, ShieldAlert } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate, useParams } from 'react-router-dom';

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
import { Branding } from '@/config/branding';
import { useAuth } from '@/contexts/auth-context';
import { useAcceptCompanyInvitation } from '@/hooks/api/useCompanyInvitations';
import { queryKeys } from '@/lib/queryKeys';
import type { CompanyInvitation, CompanyUserRole } from '@/types/company';

const ROLE_LABELS: Record<CompanyUserRole, string> = {
    owner: 'Workspace owner',
    buyer_admin: 'Buyer admin',
    buyer_member: 'Buyer member',
    buyer_requester: 'Buyer requester',
    supplier_admin: 'Supplier admin',
    supplier_estimator: 'Supplier estimator',
    finance: 'Finance',
};

function roleLabel(role: CompanyUserRole): string {
    return ROLE_LABELS[role] ?? role.replace(/_/g, ' ');
}

export function AcceptCompanyInvitationPage() {
    const { token } = useParams<{ token: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { refresh } = useAuth();
    const [invitation, setInvitation] = useState<CompanyInvitation | null>(
        null,
    );
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const hasAutoAttempted = useRef(false);

    const {
        mutate: accept,
        reset,
        isPending,
        error,
    } = useAcceptCompanyInvitation({
        onSuccess: async (data) => {
            setInvitation(data);
            setErrorMessage(null);
            await Promise.all([
                queryClient.invalidateQueries({
                    queryKey: ['company-invitations', 'list'],
                }),
                queryClient.invalidateQueries({
                    queryKey: queryKeys.me.companies(),
                }),
                queryClient.invalidateQueries({
                    queryKey: queryKeys.me.profile(),
                }),
                refresh(),
            ]);
        },
        onError: (apiError) => {
            setInvitation(null);
            setErrorMessage(
                apiError.message ?? 'Unable to accept this invitation.',
            );
        },
    });

    useEffect(() => {
        if (!token || hasAutoAttempted.current) {
            return;
        }
        hasAutoAttempted.current = true;
        accept(token);
    }, [token, accept]);

    const finalError = useMemo(() => {
        if (!token) {
            return 'Invitation token is missing.';
        }
        return errorMessage ?? error?.message ?? null;
    }, [token, errorMessage, error?.message]);

    const showSuccess = invitation !== null;
    const canRetry = Boolean(token) && !isPending;

    const handleRetry = () => {
        if (!token) {
            return;
        }
        setInvitation(null);
        setErrorMessage(null);
        reset();
        accept(token);
    };

    const handleGoToApp = () => navigate('/app');
    const handleManageOrganizations = () => navigate('/app/settings/profile');

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4 py-12">
            <Helmet>
                <title>Accept invitation • {Branding.name}</title>
            </Helmet>
            <Card className="w-full max-w-2xl shadow-lg">
                <CardHeader className="items-center text-center">
                    <img
                        src={Branding.logo.symbol}
                        alt={Branding.name}
                        className="h-10"
                    />
                    <CardTitle className="mt-2 text-2xl font-semibold text-foreground">
                        Join your workspace
                    </CardTitle>
                    <CardDescription>
                        Accept the invitation to access your company&rsquo;s
                        Elements Supply workspace.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {showSuccess ? (
                        <div className="rounded-lg border border-emerald-100 bg-emerald-50/80 p-4">
                            <div className="flex items-start gap-4">
                                <CheckCircle2
                                    className="h-6 w-6 text-emerald-600"
                                    aria-hidden="true"
                                />
                                <div className="space-y-1">
                                    <p className="text-base font-medium text-emerald-900">
                                        Invitation accepted
                                    </p>
                                    <p className="text-sm text-emerald-800">
                                        You now have{' '}
                                        {roleLabel(invitation!.role)} access to
                                        workspace #{invitation!.companyId}. Use
                                        the organization switcher in the top bar
                                        to jump into it at any time.
                                    </p>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    {!showSuccess && isPending ? (
                        <div className="flex items-center gap-3 rounded-lg border border-muted bg-background/80 p-4">
                            <Loader2
                                className="text-brand-primary h-5 w-5 animate-spin"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    Joining workspace…
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Hang tight while we confirm your membership.
                                </p>
                            </div>
                        </div>
                    ) : null}

                    {!showSuccess && !isPending && finalError ? (
                        <Alert variant="destructive">
                            <ShieldAlert
                                className="h-4 w-4"
                                aria-hidden="true"
                            />
                            <AlertDescription>{finalError}</AlertDescription>
                        </Alert>
                    ) : null}

                    {showSuccess ? (
                        <div className="rounded-lg border border-muted bg-background/60 p-4 text-sm text-muted-foreground">
                            <p>
                                We refreshed your session so your new role and
                                memberships are ready everywhere. If you were
                                invited to multiple companies, you can manage
                                defaults under Settings → Profile.
                            </p>
                        </div>
                    ) : null}
                </CardContent>
                <CardFooter className="flex flex-col gap-3 md:flex-row md:justify-end">
                    {showSuccess ? (
                        <>
                            <Button onClick={handleGoToApp}>
                                Go to workspace
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleManageOrganizations}
                            >
                                Manage organizations
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button onClick={handleGoToApp} variant="ghost">
                                Back to dashboard
                            </Button>
                            {canRetry ? (
                                <Button onClick={handleRetry} disabled={!token}>
                                    Try again
                                </Button>
                            ) : (
                                <Button disabled>Try again</Button>
                            )}
                        </>
                    )}
                </CardFooter>
            </Card>
        </div>
    );
}
