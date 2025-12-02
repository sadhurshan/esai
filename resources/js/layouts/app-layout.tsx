import { Sidebar, SidebarFooter, SidebarHeader, SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Branding } from '@/config/branding';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { BillingStatusBanner } from '@/components/billing-status-banner';
import { SidebarNav } from '@/components/sidebar-nav';
import { TopBar } from '@/components/top-bar';
import { FormattingProvider } from '@/contexts/formatting-context';
import { useAuth } from '@/contexts/auth-context';
import { useEffect } from 'react';
import { isPlatformRole } from '@/constants/platform-roles';

export function AppLayout() {
    const { state } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const role = state.user?.role ?? null;
    const isPlatformOperator = isPlatformRole(role);

    useEffect(() => {
        if (!isPlatformOperator) {
            return;
        }

        if (location.pathname === '/app' || location.pathname === '/app/') {
            navigate('/app/admin', { replace: true });
        }
    }, [isPlatformOperator, location.pathname, navigate]);

    return (
        <FormattingProvider>
            <SidebarProvider>
                <div className="flex min-h-screen w-full bg-muted/20">
                    <Sidebar variant="inset">
                        <SidebarHeader className="px-4 py-3">
                            <img src={Branding.logo.default} alt={Branding.name} className="h-12 w-fit" />
                        </SidebarHeader>
                        <SidebarNav />
                        <SidebarFooter className="px-4 py-6 text-xs text-muted-foreground">
                            <p>&copy; {new Date().getFullYear()} {Branding.name}</p>
                        </SidebarFooter>
                    </Sidebar>
                    <SidebarInset>
                        <TopBar />
                        <BillingStatusBanner />
                        <PlanUpgradeBanner />
                        <main className="flex flex-1 flex-col overflow-y-auto px-4 pb-8 pt-4">
                            <Outlet />
                        </main>
                    </SidebarInset>
                </div>
            </SidebarProvider>
        </FormattingProvider>
    );
}
