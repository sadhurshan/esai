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
import { home, orders, rfq, suppliers, purchaseOrders } from '@/routes';
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
    const isSupplierUser =
        typeof userRole === 'string' && userRole.startsWith('supplier_');
    const isPlatformAdmin =
        userRole === 'platform_super' || userRole === 'platform_support';

    const mainNavItems = useMemo<NavItem[]>(() => {
        const purchaseOrderNav: NavItem = {
            title: 'Purchase Orders',
            href: purchaseOrders.index(),
            icon: Truck,
        };

        if (isSupplierUser) {
            purchaseOrderNav.children = [
                {
                    title: 'Supplier POs',
                    href: purchaseOrders.supplier.index(),
                    icon: Package,
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
            },
            {
                title: 'RFQ',
                href: rfq.index(),
                icon: FileSpreadsheet,
            },
            {
                title: 'Orders',
                href: orders.index(),
                icon: PackageCheck,
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

        items.push({
            title: 'Resource Center',
            href: home(),
            icon: BookMarked,
            disabled: true,
        });

        return items;
    }, [isPlatformAdmin, isSupplierUser]);

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
