import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { LayoutDashboard } from 'lucide-react';

export function DashboardPage() {
    return (
        <ModulePlaceholder
            moduleKey="dashboard"
            title="Operations dashboard"
            description="High-level KPIs, active sourcing events, and tasks will render here once the dashboard widgets are wired to live data."
            hero={<LayoutDashboard className="h-12 w-12" />}
        />
    );
}
