import { ShieldAlert } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { Outlet, useNavigate } from 'react-router-dom';

import { EmptyState } from '@/components/empty-state';
import { useAuth } from '@/contexts/auth-context';

export function RequireSupplierAccess() {
    const { state, activePersona } = useAuth();
    const navigate = useNavigate();
    const status = (state.company?.supplier_status ?? 'none') as string;

    const isSupplierPersona = activePersona?.type === 'supplier';
    const isSupplierStart =
        state.company?.start_mode === 'supplier' ||
        (status && status !== 'none');

    if (!isSupplierPersona && !isSupplierStart && status !== 'approved') {
        return (
            <section className="mx-auto flex w-full max-w-3xl flex-col gap-6 py-10">
                <Helmet>
                    <title>Supplier access required • Elements Supply</title>
                </Helmet>
                <EmptyState
                    title="Supplier access required"
                    description="This section is available only to companies approved as suppliers. Owners can submit or review the application from Settings."
                    icon={<ShieldAlert className="h-12 w-12" />}
                    ctaLabel="Review supplier application"
                    ctaProps={{
                        onClick: () => navigate('/app/settings'),
                    }}
                />
            </section>
        );
    }

    return <Outlet />;
}
