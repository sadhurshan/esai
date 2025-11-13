import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { Boxes } from 'lucide-react';

export function InventoryPage() {
    return (
        <ModulePlaceholder
            moduleKey="inventory"
            title="Inventory"
            description="Track stock levels, locations, and reorder points. This placeholder keeps the route alive until the inventory UI is complete."
            hero={<Boxes className="h-12 w-12" />}
        />
    );
}
