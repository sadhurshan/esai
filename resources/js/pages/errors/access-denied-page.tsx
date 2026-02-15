import { EmptyState } from '@/components/empty-state';
import { Branding } from '@/config/branding';
import { ShieldAlert } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { useLocation, useNavigate } from 'react-router-dom';

export function AccessDeniedPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const from = (location.state as { from?: string } | null)?.from ?? '/app';

    return (
        <section className="mx-auto flex w-full max-w-3xl flex-col gap-6 py-10">
            <Helmet>
                <title>Access Denied • {Branding.name}</title>
            </Helmet>
            <EmptyState
                title="You do not have access"
                description="Your current role or plan does not include this feature. If you believe this is an error, please contact your workspace administrator."
                icon={<ShieldAlert className="h-12 w-12" />}
                ctaLabel="Return to dashboard"
                ctaProps={{
                    onClick: () => navigate(from),
                }}
            />
            <div className="text-center text-xs text-muted-foreground">
                <p>
                    Need additional access? Reach out to your administrator or
                    upgrade via Billing once the entitlements module is
                    available.
                </p>
            </div>
        </section>
    );
}
