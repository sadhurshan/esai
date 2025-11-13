import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { NavLink, matchPath, useLocation } from 'react-router-dom';

interface BreadcrumbDefinition {
    path: string;
    label: string;
    dynamicLabel?: (params: Record<string, string | undefined>) => string;
}

const ROUTE_BREADCRUMBS: BreadcrumbDefinition[] = [
    { path: '/app', label: 'Dashboard' },
    { path: '/app/rfqs', label: 'RFQs' },
    {
        path: '/app/rfqs/:rfqId',
        label: 'RFQ Detail',
        dynamicLabel: (params) => `RFQ #${params.rfqId}`,
    },
    { path: '/app/quotes', label: 'Quotes' },
    { path: '/app/purchase-orders', label: 'Purchase Orders' },
    {
        path: '/app/purchase-orders/:purchaseOrderId',
        label: 'Purchase Order Detail',
        dynamicLabel: (params) => `PO #${params.purchaseOrderId}`,
    },
    { path: '/app/invoices', label: 'Invoices' },
    { path: '/app/inventory', label: 'Inventory' },
    { path: '/app/assets', label: 'Assets' },
    { path: '/app/orders', label: 'Orders' },
    { path: '/app/risk', label: 'Risk & ESG' },
    { path: '/app/analytics', label: 'Analytics' },
    { path: '/app/settings', label: 'Settings' },
    { path: '/app/admin', label: 'Admin Console' },
];

export function WorkspaceBreadcrumbs() {
    const location = useLocation();

    const segments = location.pathname
        .split('/')
        .filter(Boolean)
        .map((_, index, array) => `/${array.slice(0, index + 1).join('/')}`);

    const crumbs = segments
        .map((segmentPath) => {
            const definition = ROUTE_BREADCRUMBS.find((item) => matchPath({ path: item.path, end: true }, segmentPath));
            if (!definition) {
                return null;
            }

            const match = matchPath({ path: definition.path, end: true }, segmentPath);
            const label = definition.dynamicLabel && match?.params ? definition.dynamicLabel(match.params) : definition.label;

            return {
                href: segmentPath,
                label,
            };
        })
        .filter((crumb): crumb is { href: string; label: string } => Boolean(crumb));

    if (crumbs.length === 0) {
        return null;
    }

    const lastIndex = crumbs.length - 1;

    return (
        <Breadcrumb>
            <BreadcrumbList>
                {crumbs.map((crumb, index) => (
                    <BreadcrumbItem key={crumb.href}>
                        {index === lastIndex ? (
                            <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                        ) : (
                            <BreadcrumbLink asChild>
                                <NavLink to={crumb.href}>{crumb.label}</NavLink>
                            </BreadcrumbLink>
                        )}
                        {index !== lastIndex ? <BreadcrumbSeparator /> : null}
                    </BreadcrumbItem>
                ))}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
