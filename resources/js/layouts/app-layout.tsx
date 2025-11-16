import { Sidebar, SidebarFooter, SidebarHeader, SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Branding } from '@/config/branding';
import { Outlet } from 'react-router-dom';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { SidebarNav } from '@/components/sidebar-nav';
import { TopBar } from '@/components/top-bar';
import { FormattingProvider } from '@/contexts/formatting-context';

export function AppLayout() {
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
