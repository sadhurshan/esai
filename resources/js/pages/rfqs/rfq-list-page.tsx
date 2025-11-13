import { ModulePlaceholder } from '@/pages/shared/module-placeholder';
import { Files } from 'lucide-react';

export function RfqListPage() {
    return (
        <ModulePlaceholder
            moduleKey="rfqs"
            title="Requests for Quotation"
            description="Create, filter, and collaborate on sourcing events. This stub confirms the RFQ SDK wiring until the full grid experience is implemented."
            hero={<Files className="h-12 w-12" />}
        />
    );
}
