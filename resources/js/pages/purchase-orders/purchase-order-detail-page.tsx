import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { ClipboardSignature } from 'lucide-react';

export function PurchaseOrderDetailPage() {
    return (
        <ModulePlaceholder
            moduleKey="purchase-order-detail"
            title="Purchase order detail"
            description="Line item fulfilment, receipts, and change orders will be implemented here in accordance with the PO deep spec."
            hero={<ClipboardSignature className="h-12 w-12" />}
        />
    );
}
