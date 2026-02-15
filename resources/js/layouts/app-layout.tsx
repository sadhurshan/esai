import { BillingStatusBanner } from '@/components/billing-status-banner';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { SidebarNav } from '@/components/sidebar-nav';
import { TopBar } from '@/components/top-bar';
import {
    Sidebar,
    SidebarFooter,
    SidebarHeader,
    SidebarInset,
    SidebarProvider,
} from '@/components/ui/sidebar';
import { Branding } from '@/config/branding';
import { isPlatformRole } from '@/constants/platform-roles';
import { useAuth } from '@/contexts/auth-context';
import { FormattingProvider } from '@/contexts/formatting-context';
import { useEffect } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';

const SUPPLIER_ALLOWED_PREFIXES = [
    '/app/rfqs',
    '/app/quotes',
    '/app/supplier',
    '/app/purchase-orders',
    '/app/pos',
    '/app/downloads',
    '/app/settings',
];
const SUPPLIER_ONLY_PREFIXES = ['/app/supplier/'];
const SUPPLIER_REDIRECT_PATH = '/app/supplier';
const BUYER_REDIRECT_PATH = '/app';

export function AppLayout() {
    const { state, activePersona } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const role = state.user?.role ?? null;
    const isPlatformOperator = isPlatformRole(role);
    const isSupplierPersona = activePersona?.type === 'supplier';
    const isSupplierStart = state.company?.start_mode === 'supplier';
    const isSupplierMode = isSupplierPersona || isSupplierStart;

    useEffect(() => {
        if (!isPlatformOperator) {
            return;
        }

        if (location.pathname === '/app' || location.pathname === '/app/') {
            navigate('/app/admin', { replace: true });
        }
    }, [isPlatformOperator, location.pathname, navigate]);

    useEffect(() => {
        if (isPlatformOperator) {
            return;
        }

        if (isSupplierMode) {
            if (location.pathname === '/app' || location.pathname === '/app/') {
                navigate('/app/supplier', {
                    replace: true,
                    state: { from: location.pathname },
                });
                return;
            }

            if (
                location.pathname === '/app/quotes' ||
                location.pathname === '/app/quotes/'
            ) {
                navigate('/app/supplier/quotes', {
                    replace: true,
                    state: { from: location.pathname },
                });
                return;
            }

            const allowed = SUPPLIER_ALLOWED_PREFIXES.some((prefix) =>
                location.pathname.startsWith(prefix),
            );
            if (!allowed && location.pathname !== SUPPLIER_REDIRECT_PATH) {
                navigate(SUPPLIER_REDIRECT_PATH, {
                    replace: true,
                    state: { from: location.pathname },
                });
            }
            return;
        }

        const isSupplierRoute = SUPPLIER_ONLY_PREFIXES.some((prefix) =>
            location.pathname.startsWith(prefix),
        );
        if (isSupplierRoute && location.pathname !== BUYER_REDIRECT_PATH) {
            navigate(BUYER_REDIRECT_PATH, {
                replace: true,
                state: { from: location.pathname },
            });
        }
    }, [isPlatformOperator, isSupplierMode, location.pathname, navigate]);

    return (
        <FormattingProvider>
            <SidebarProvider>
                <div className="flex min-h-screen w-full bg-muted/20">
                    <Sidebar variant="inset">
                        <SidebarHeader className="px-4 py-3">
                            <img
                                src={Branding.logo.whiteText}
                                alt={Branding.name}
                                className="h-12 w-fit"
                            />
                        </SidebarHeader>
                        <SidebarNav />
                        <SidebarFooter className="px-4 py-6 text-xs text-muted-foreground">
                            <p>
                                &copy; {new Date().getFullYear()}{' '}
                                {Branding.name}
                            </p>
                        </SidebarFooter>
                    </Sidebar>
                    <SidebarInset>
                        <TopBar />
                        <BillingStatusBanner />
                        <PlanUpgradeBanner />
                        <main className="flex flex-1 flex-col overflow-y-auto px-4 pt-4 pb-8">
                            <Outlet />
                        </main>
                    </SidebarInset>
                </div>
            </SidebarProvider>
        </FormattingProvider>
    );
}
