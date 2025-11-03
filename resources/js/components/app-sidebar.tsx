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
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    BookMarked,
    BookOpen,
    Factory,
    FileSpreadsheet,
    Folder,
    Home as HomeIcon,
    PackageCheck,
    Truck,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
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
    {
        title: 'Purchase Orders',
        href: purchaseOrders.index(),
        icon: Truck,
    },
    {
        title: 'Resource Center',
        href: home(),
        icon: BookMarked,
        disabled: true,
    },
];

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
