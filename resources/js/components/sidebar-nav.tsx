import {
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import {
    LayoutDashboard,
    FileSpreadsheet,
    FileText,
    ClipboardList,
    Wallet,
    Boxes,
    Factory,
    PackageSearch,
    ShieldCheck,
    LineChart,
    Settings,
    ShieldAlert,
    Scale,
} from 'lucide-react';
import { useMemo, type ComponentType } from 'react';
import { NavLink, matchPath, useLocation } from 'react-router-dom';
import { useAuth } from '@/contexts/auth-context';

interface NavItem {
    label: string;
    to: string;
    icon: ComponentType<{ className?: string }>;
    featureKey?: string;
    roles?: string[];
    matchExact?: boolean;
    disabledMessage?: string;
}

// TODO: confirm feature flag keys against /docs/billing_plans.md once frontend gating matrix is finalised.
const NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard', to: '/app', icon: LayoutDashboard, matchExact: true },
    { label: 'RFQs', to: '/app/rfqs', icon: FileSpreadsheet },
    { label: 'Quotes', to: '/app/quotes', icon: FileText },
    { label: 'Purchase Orders', to: '/app/purchase-orders', icon: ClipboardList },
    { label: 'Invoices', to: '/app/invoices', icon: Wallet },
    { label: 'Matching', to: '/app/matching', icon: Scale, featureKey: 'finance_enabled' },
    { label: 'Inventory', to: '/app/inventory', icon: Boxes, featureKey: 'inventory.access' },
    { label: 'Assets', to: '/app/assets', icon: Factory, featureKey: 'digital_twin.access' },
    { label: 'Orders', to: '/app/orders', icon: PackageSearch },
    { label: 'Risk & ESG', to: '/app/risk', icon: ShieldAlert, featureKey: 'risk.access' },
    { label: 'Analytics', to: '/app/analytics', icon: LineChart, featureKey: 'analytics.access' },
    { label: 'Settings', to: '/app/settings', icon: Settings },
    { label: 'Admin Console', to: '/app/admin', icon: ShieldCheck, roles: ['platform_super', 'platform_support'] },
];

export function SidebarNav() {
    const { hasFeature, state } = useAuth();
    const location = useLocation();
    const role = state.user?.role ?? null;

    const items = useMemo(() => {
        return NAV_ITEMS.filter((item) => {
            if (item.roles && (!role || !item.roles.includes(role))) {
                return false;
            }

            if (item.featureKey && !hasFeature(item.featureKey)) {
                return false;
            }

            return true;
        });
    }, [hasFeature, role]);

    return (
        <SidebarContent>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>Workspace</SidebarGroupLabel>
                <SidebarMenu>
                    {items.map((item) => {
                        const match = matchPath({ path: item.to, end: item.matchExact ?? false }, location.pathname);
                        const Icon = item.icon;

                        return (
                            <SidebarMenuItem key={item.to}>
                                <SidebarMenuButton asChild isActive={Boolean(match)} tooltip={item.disabledMessage}>
                                    <NavLink
                                        to={item.to}
                                        className={({ isActive }) =>
                                            cn(
                                                'flex items-center gap-2',
                                                isActive ? 'text-sidebar-accent-foreground' : undefined,
                                            )
                                        }
                                    >
                                        <Icon className="h-4 w-4" />
                                        <span className="truncate text-sm">{item.label}</span>
                                    </NavLink>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>
    );
}
