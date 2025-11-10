import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home } from '@/routes';
import rfq from '@/routes/rfq';
import suppliers from '@/routes/suppliers';
import purchaseOrders from '@/routes/purchase-orders';
import orders  from '@/routes/orders';
import { registration as companyRegistration } from '@/routes/company';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    BookMarked,
    BookOpen,
    Factory,
    FileSpreadsheet,
    Folder,
    Home as HomeIcon,
    IdCard,
    PackageCheck,
    Package,
    ShieldCheck,
    ShieldAlert,
    Truck,
} from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const page = usePage<SharedData>();
    const userRole = page.props.auth?.user?.role ?? null;
    const requiresCompanyOnboarding = page.props.auth?.user?.requires_company_onboarding ?? false;
    const isSupplierUser =
        typeof userRole === 'string' && userRole.startsWith('supplier_');
    const isPlatformAdmin =
        userRole === 'platform_super' || userRole === 'platform_support';
    // Lock buyer navigation while onboarding remains incomplete for the active tenant.
    const onboardingLockActive = requiresCompanyOnboarding && !isPlatformAdmin;

    const mainNavItems = useMemo<NavItem[]>(() => {
        const purchaseOrderNav: NavItem = {
            title: 'Purchase Orders',
            href: purchaseOrders.index(),
            icon: Truck,
            disabled: onboardingLockActive,
        };

        if (isSupplierUser) {
            purchaseOrderNav.children = [
                {
                    title: 'Supplier POs',
                    href: purchaseOrders.supplier.index(),
                    icon: Package,
                    disabled: onboardingLockActive,
                },
            ];
        }

        const items: NavItem[] = [
            {
                title: 'Home',
                href: home(),
                icon: HomeIcon,
            },
            {
                title: 'Supplier Directory',
                href: suppliers.index(),
                icon: Factory,
                disabled: onboardingLockActive,
            },
            {
                title: 'RFQ',
                href: rfq.index(),
                icon: FileSpreadsheet,
                disabled: onboardingLockActive,
            },
            {
                title: 'Orders',
                href: orders.index(),
                icon: PackageCheck,
                disabled: onboardingLockActive,
            },
            purchaseOrderNav,
        ];

        if (isSupplierUser) {
            items.push({
                title: 'Company Profile',
                href: '/supplier/company-profile',
                icon: IdCard,
            });
        }

        if (isPlatformAdmin) {
            items.push({
                title: 'Tenant Approvals',
                href: '/admin/companies',
                icon: ShieldCheck,
            });
        }

        if (onboardingLockActive) {
            items.unshift({
                title: 'Complete Onboarding',
                href: companyRegistration.url(),
                icon: ShieldAlert,
            });
        }

        items.push({
            title: 'Resource Center',
            href: home(),
            icon: BookMarked,
            disabled: true,
        });

        return items;
    }, [isPlatformAdmin, isSupplierUser, onboardingLockActive]);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={home()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
