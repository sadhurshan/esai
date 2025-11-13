import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { ClipboardList } from 'lucide-react';

export function PurchaseOrderListPage() {
    return (
        <ModulePlaceholder
            moduleKey="purchase-orders"
            title="Purchase orders"
            description="Author, approve, and track purchase orders. This stub keeps the PO module wired until the full workflow UI lands."
            hero={<ClipboardList className="h-12 w-12" />}
        />
    );
}
