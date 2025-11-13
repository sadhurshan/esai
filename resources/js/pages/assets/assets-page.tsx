import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { Factory } from 'lucide-react';

export function AssetsPage() {
    return (
        <ModulePlaceholder
            moduleKey="assets"
            title="Assets"
            description="Digital twin tracking, maintenance, and lifecycle data will appear here after the assets module is built."
            hero={<Factory className="h-12 w-12" />}
        />
    );
}
