import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { PackageSearch } from 'lucide-react';

export function OrdersPage() {
    return (
        <ModulePlaceholder
            moduleKey="orders"
            title="Orders"
            description="Fulfilment, shipments, and milestone tracking will render here when the downstream logistics module is wired."
            hero={<PackageSearch className="h-12 w-12" />}
        />
    );
}
