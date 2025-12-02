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
    Building2,
    Layers2,
    ListChecks,
    KeyRound,
    RadioTower,
    Activity,
    ScrollText,
    Users,
    FolderTree,
    DownloadCloud,
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
    requiresAdminConsole?: boolean;
}

const PLATFORM_ROLES = new Set(['platform_super', 'platform_support']);

// TODO: confirm feature flag keys against /docs/billing_plans.md once frontend gating matrix is finalised.
const WORKSPACE_NAV_ITEMS: NavItem[] = [
    { label: 'Dashboard', to: '/app', icon: LayoutDashboard, matchExact: true },
    { label: 'RFQs', to: '/app/rfqs', icon: FileSpreadsheet },
    { label: 'Quotes', to: '/app/quotes', icon: FileText },
    { label: 'Purchase Orders', to: '/app/purchase-orders', icon: ClipboardList },
    { label: 'Invoices', to: '/app/invoices', icon: Wallet },
    { label: 'Matching', to: '/app/matching', icon: Scale, featureKey: 'finance_enabled' },
    { label: 'Inventory', to: '/app/inventory', icon: Boxes, featureKey: 'inventory.access' },
    {
        label: 'Digital Twin Library',
        to: '/app/library/digital-twins',
        icon: Factory,
    },
    { label: 'Orders', to: '/app/orders', icon: PackageSearch },
    { label: 'Suppliers', to: '/app/suppliers', icon: Users },
    { label: 'Download Center', to: '/app/downloads', icon: DownloadCloud },
    { label: 'Risk & ESG', to: '/app/risk', icon: ShieldAlert, featureKey: 'risk.access' },
    { label: 'Analytics', to: '/app/analytics', icon: LineChart, featureKey: 'analytics.access' },
    { label: 'Settings', to: '/app/settings', icon: Settings },
    { label: 'Admin Console', to: '/app/admin', icon: ShieldCheck, requiresAdminConsole: true },
];

const ADMIN_NAV_ITEMS: NavItem[] = [
    { label: 'Admin Dashboard', to: '/app/admin', icon: ShieldCheck, requiresAdminConsole: true, matchExact: true },
    { label: 'Company Approvals', to: '/app/admin/company-approvals', icon: Building2, requiresAdminConsole: true },
    { label: 'Supplier Applications', to: '/app/admin/supplier-applications', icon: Users, requiresAdminConsole: true },
    { label: 'Plans & Features', to: '/app/admin/plans', icon: Layers2, requiresAdminConsole: true },
    { label: 'Digital Twins', to: '/app/admin/digital-twins', icon: Factory, requiresAdminConsole: true },
    { label: 'Twin Categories', to: '/app/admin/digital-twins/categories', icon: FolderTree, requiresAdminConsole: true },
    { label: 'Roles & Permissions', to: '/app/admin/roles', icon: ListChecks, requiresAdminConsole: true },
    { label: 'API Keys', to: '/app/admin/api-keys', icon: KeyRound, requiresAdminConsole: true },
    { label: 'Webhooks', to: '/app/admin/webhooks', icon: RadioTower, requiresAdminConsole: true },
    { label: 'Rate Limits', to: '/app/admin/rate-limits', icon: Activity, requiresAdminConsole: true },
    { label: 'Audit Log', to: '/app/admin/audit', icon: ScrollText, requiresAdminConsole: true },
];

export function SidebarNav() {
    const { hasFeature, state, canAccessAdminConsole } = useAuth();
    const location = useLocation();
    const role = state.user?.role ?? null;
    const isPlatformOperator = role ? PLATFORM_ROLES.has(role) : false;

    const items = useMemo(() => {
        const sourceItems = isPlatformOperator ? ADMIN_NAV_ITEMS : WORKSPACE_NAV_ITEMS;
        return sourceItems.filter((item) => {
            if (item.requiresAdminConsole && !canAccessAdminConsole) {
                return false;
            }

            if (item.roles && (!role || !item.roles.includes(role))) {
                return false;
            }

            if (item.featureKey && !hasFeature(item.featureKey)) {
                return false;
            }

            return true;
        });
    }, [canAccessAdminConsole, hasFeature, isPlatformOperator, role]);

    return (
        <SidebarContent>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>{isPlatformOperator ? 'Admin Console' : 'Workspace'}</SidebarGroupLabel>
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
