import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { ShieldCheck } from 'lucide-react';

export function AdminConsolePage() {
    return (
        <ModulePlaceholder
            moduleKey="admin-console"
            title="Admin console"
            description="Platform-only tooling for provisioning tenants and monitoring background jobs will surface here for privileged roles."
            hero={<ShieldCheck className="h-12 w-12" />}
        />
    );
}
