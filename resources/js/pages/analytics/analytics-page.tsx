import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { LineChart } from 'lucide-react';

export function AnalyticsPage() {
    return (
        <ModulePlaceholder
            moduleKey="analytics"
            title="Analytics"
            description="Benchmarking dashboards and predictive sourcing insights will appear here once analytics widgets are implemented."
            hero={<LineChart className="h-12 w-12" />}
        />
    );
}
